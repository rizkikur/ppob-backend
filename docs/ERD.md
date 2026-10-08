# ERD — PPOB Backend

Dokumen ini mendeskripsikan semua tabel database beserta relasi, tipe kolom, constraint, dan catatan penting.
Setiap perubahan skema **harus diperbarui di dokumen ini terlebih dahulu** sebelum membuat migration.

**Terakhir diperbarui:** 2026-10-05 — Sinkron dengan ADR-002 s/d ADR-010.

---

## Daftar Tabel per Domain

| Domain        | Tabel                                                                                                   |
|---------------|---------------------------------------------------------------------------------------------------------|
| Auth          | `users`, `otp_codes`, `personal_access_tokens`                                                          |
| Security      | `pin_verification_tokens`                                                                               |
| Wallet        | `wallets`, `wallet_mutations`, `topup_requests`                                                         |
| Product       | `product_categories`, `providers`, `products`, `product_tier_prices`                                    |
| Inquiry       | `inquiries`                                                                                             |
| Transaction   | `transactions` (partitioned), `idempotency_keys`                                                        |
| PPOB          | `processed_webhook_events`, `product_supplier_routes`, `partner_routing_rules`, `supplier_balances`     |
| Partner       | `partners`, `partner_logs`, `partner_product_prices`, `webhook_deliveries`                              |
| Admin         | (pakai `users` dengan `role=admin`, route `/admin/*`)                                                   |
| Monitoring    | `reconciliation_reports`, `reconciliation_discrepancies`                                                |
| Shared        | `user_tiers`                                                                                            |

---

## Domain: Auth & User

### `user_tiers`
| Kolom         | Tipe               | Constraint        | Keterangan                              |
|---------------|--------------------|-------------------|-----------------------------------------|
| id            | bigserial          | PK                |                                         |
| name          | varchar(50)        | NOT NULL, UNIQUE  | Contoh: `end_user`, `agent`, `reseller` |
| label         | varchar(100)       | NOT NULL          | Label tampilan                          |
| description   | text               | NULLABLE          |                                         |
| created_at    | timestamptz        | NOT NULL          |                                         |
| updated_at    | timestamptz        | NOT NULL          |                                         |

### `users`
| Kolom         | Tipe               | Constraint              | Keterangan                                      |
|---------------|--------------------|-------------------------|-------------------------------------------------|
| id            | bigserial          | PK                      |                                                 |
| user_tier_id  | bigint             | FK → user_tiers.id      | Default: `end_user`                             |
| name          | varchar(100)       | NOT NULL                |                                                 |
| phone         | varchar(20)        | NOT NULL, UNIQUE        | Format E.164, contoh: `+6281234567890`          |
| email         | varchar(150)       | NULLABLE, UNIQUE        |                                                 |
| pin_hash      | varchar(255)       | NULLABLE                | bcrypt hash dari 6-digit PIN                    |
| role          | varchar(20)        | NOT NULL, DEFAULT 'user'| `user` / `admin` / `super_admin` (ADR-006)      |
| is_active     | boolean            | NOT NULL, DEFAULT true  |                                                 |
| is_verified   | boolean            | NOT NULL, DEFAULT false | true setelah OTP pertama berhasil               |
| created_at    | timestamptz        | NOT NULL                |                                                 |
| updated_at    | timestamptz        | NOT NULL                |                                                 |

**Index:** `phone`, `email`, `role`

### `otp_codes`
| Kolom        | Tipe        | Constraint              | Keterangan                                    |
|--------------|-------------|-------------------------|-----------------------------------------------|
| id           | bigserial   | PK                      |                                               |
| phone        | varchar(20) | NOT NULL                |                                               |
| code         | varchar(10) | NOT NULL                | 6-digit OTP                                   |
| type         | varchar(30) | NOT NULL                | `register`, `login`, `reset_pin`              |
| attempts     | smallint    | NOT NULL, DEFAULT 0     | Maks 3 percobaan sebelum OTP dibatalkan       |
| expires_at   | timestamptz | NOT NULL                | Default: now() + 5 menit                      |
| verified_at  | timestamptz | NULLABLE                |                                               |
| created_at   | timestamptz | NOT NULL                |                                               |

**Index:** `(phone, type, verified_at)` — untuk cari OTP aktif

### `personal_access_tokens`
Dikelola oleh Laravel Sanctum secara otomatis.

---

## Domain: Security

### `pin_verification_tokens`
| Kolom        | Tipe        | Constraint            | Keterangan                                              |
|--------------|-------------|-----------------------|---------------------------------------------------------|
| id           | bigserial   | PK                    |                                                         |
| user_id      | bigint      | FK → users.id         |                                                         |
| token        | varchar(64) | NOT NULL, UNIQUE      | Random hex 32-byte, satu kali pakai                     |
| purpose      | varchar(50) | NOT NULL              | `transaction`, `change_pin`, dsb.                       |
| used_at      | timestamptz | NULLABLE              | Set saat token dikonsumsi                               |
| expires_at   | timestamptz | NOT NULL              | Default: now() + 5 menit                                |
| created_at   | timestamptz | NOT NULL              |                                                         |

**Index:** `token`, `(user_id, used_at, expires_at)`

---

## Domain: Wallet

### `wallets`
| Kolom           | Tipe        | Constraint              | Keterangan                                 |
|-----------------|-------------|-------------------------|--------------------------------------------|
| id              | bigserial   | PK                      |                                            |
| user_id         | bigint      | FK → users.id, UNIQUE   | Satu wallet per user                       |
| balance_cents   | bigint      | NOT NULL, DEFAULT 0     | Saldo dalam satuan sen (cents)             |
| created_at      | timestamptz | NOT NULL                |                                            |
| updated_at      | timestamptz | NOT NULL                |                                            |

**Aturan:** Saldo TIDAK BOLEH negatif. Enforce via CHECK constraint: `balance_cents >= 0`.

### `wallet_mutations`
| Kolom               | Tipe         | Constraint           | Keterangan                                                      |
|---------------------|--------------|----------------------|-----------------------------------------------------------------|
| id                  | bigserial    | PK                   |                                                                 |
| wallet_id           | bigint       | FK → wallets.id      |                                                                 |
| user_id             | bigint       | FK → users.id        | Denormalisasi untuk query cepat                                 |
| type                | varchar(20)  | NOT NULL             | `credit` (masuk), `debit` (keluar)                             |
| amount_cents        | bigint       | NOT NULL, CHECK > 0  | Selalu positif, type yang menentukan arah                       |
| balance_after_cents | bigint       | NOT NULL             | Snapshot saldo setelah mutasi                                   |
| reference_type      | varchar(50)  | NOT NULL             | `topup`, `transaction`, `refund`, `adjustment`                  |
| reference_id        | bigint       | NULLABLE             | ID baris di tabel referensi                                     |
| note                | text         | NULLABLE             |                                                                 |
| created_at          | timestamptz  | NOT NULL             |                                                                 |

**APPEND-ONLY:** Terdapat trigger database yang menolak UPDATE dan DELETE pada tabel ini.
**Index:** `(wallet_id, created_at DESC)`, `(reference_type, reference_id)`

```sql
-- Trigger append-only untuk wallet_mutations
CREATE OR REPLACE FUNCTION prevent_wallet_mutations_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'wallet_mutations is append-only: UPDATE and DELETE are not allowed';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER wallet_mutations_immutable
    BEFORE UPDATE OR DELETE ON wallet_mutations
    FOR EACH ROW EXECUTE FUNCTION prevent_wallet_mutations_modification();
```

### `topup_requests`
| Kolom               | Tipe         | Constraint         | Keterangan                                                  |
|---------------------|--------------|--------------------|-------------------------------------------------------------|
| id                  | bigserial    | PK                 |                                                             |
| user_id             | bigint       | FK → users.id      |                                                             |
| amount_cents        | bigint       | NOT NULL, CHECK > 0|                                                             |
| method              | varchar(30)  | NOT NULL           | `payment_gateway`, `manual_transfer`                        |
| payment_gateway     | varchar(30)  | NULLABLE           | `midtrans`, `xendit`, `doku`                                |
| gateway_ref         | varchar(100) | NULLABLE           | ID transaksi dari payment gateway                           |
| gateway_payload     | jsonb        | NULLABLE           | Response mentah dari gateway                                |
| status              | varchar(20)  | NOT NULL           | `pending`, `paid`, `failed`, `expired`                      |
| idempotency_key     | varchar(100) | NULLABLE, UNIQUE   |                                                             |
| paid_at             | timestamptz  | NULLABLE           |                                                             |
| confirmed_by        | bigint       | NULLABLE           | FK → users.id (admin yang konfirmasi)                       |
| confirmed_at        | timestamptz  | NULLABLE           |                                                             |
| created_at          | timestamptz  | NOT NULL           |                                                             |
| updated_at          | timestamptz  | NOT NULL           |                                                             |

**Index:** `(user_id, status)`, `gateway_ref`

---

## Domain: Product

### `product_categories`
| Kolom      | Tipe         | Constraint              | Keterangan                                                       |
|------------|--------------|-------------------------|------------------------------------------------------------------|
| id         | bigserial    | PK                      |                                                                  |
| name       | varchar(100) | NOT NULL                | `Pulsa`, `Paket Data`, `Token Listrik`, `Tagihan Listrik`, dsb. |
| code       | varchar(30)  | NOT NULL, UNIQUE        | `pulsa`, `paket_data`, `token_listrik`, `tagihan_listrik`, `pdam`|
| icon_url   | varchar(255) | NULLABLE                |                                                                  |
| sort_order | smallint     | NOT NULL DEFAULT 0      |                                                                  |
| is_active  | boolean      | NOT NULL DEFAULT true   |                                                                  |
| created_at | timestamptz  | NOT NULL                |                                                                  |
| updated_at | timestamptz  | NOT NULL                |                                                                  |

### `providers`
| Kolom                          | Tipe         | Constraint              | Keterangan                                              |
|--------------------------------|--------------|-------------------------|---------------------------------------------------------|
| id                             | bigserial    | PK                      |                                                         |
| name                           | varchar(100) | NOT NULL                | `Telkomsel`, `PLN`, `PDAM Kota X`, dsb.                |
| code                           | varchar(30)  | NOT NULL, UNIQUE        | `telkomsel`, `pln`, `pdam_bandung`, dsb.                |
| driver                         | varchar(50)  | NOT NULL                | Nama class driver, misal: `TelkomselDriver`             |
| config                         | jsonb        | NULLABLE                | API endpoint, credentials (dienkripsi di aplikasi)      |
| queue_name                     | varchar(50)  | NOT NULL                | Nama queue Horizon, misal: `supplier_telkomsel` (ADR-002)|
| max_workers                    | smallint     | NOT NULL DEFAULT 10     | Jumlah worker Horizon untuk supplier ini (ADR-002)       |
| rate_limit_per_minute          | integer      | NOT NULL DEFAULT 60     | Maks request/menit ke API supplier (ADR-002)            |
| timeout_seconds                | smallint     | NOT NULL DEFAULT 30     | Timeout HTTP call ke supplier (ADR-002)                 |
| priority                       | smallint     | NOT NULL DEFAULT 1      | Urutan preferensi, 1 = tertinggi (ADR-002)              |
| supports_balance_api           | boolean      | NOT NULL DEFAULT false  | Apakah supplier punya API cek saldo? (ADR-009)          |
| balance_alert_threshold_cents  | bigint       | NULLABLE                | Alert jika saldo < nilai ini, null = nonaktif (ADR-009) |
| is_active                      | boolean      | NOT NULL DEFAULT true   |                                                         |
| created_at                     | timestamptz  | NOT NULL                |                                                         |
| updated_at                     | timestamptz  | NOT NULL                |                                                         |

### `products`
| Kolom            | Tipe         | Constraint              | Keterangan                                                  |
|------------------|--------------|-------------------------|-------------------------------------------------------------|
| id               | bigserial    | PK                      |                                                             |
| provider_id      | bigint       | FK → providers.id       |                                                             |
| category_id      | bigint       | FK → product_categories.id |                                                          |
| sku_code         | varchar(50)  | NOT NULL, UNIQUE        | Contoh: `TLS-5000`, `PLN-TOKEN`, `PDAM-BDNG`               |
| name             | varchar(150) | NOT NULL                |                                                             |
| description      | text         | NULLABLE                |                                                             |
| product_type     | varchar(10)  | NOT NULL                | `prepaid` atau `postpaid`                                   |
| base_price_cents | bigint       | NOT NULL                | Harga modal dari provider (dalam cents)                     |
| admin_fee_cents  | bigint       | NOT NULL DEFAULT 0      | Biaya admin tetap                                           |
| is_active        | boolean      | NOT NULL DEFAULT true   |                                                             |
| created_at       | timestamptz  | NOT NULL                |                                                             |
| updated_at       | timestamptz  | NOT NULL                |                                                             |

**Aturan penting:**
- `product_type = 'prepaid'`: harga jual dari server, `amount` dari klien **diabaikan**
- `product_type = 'postpaid'`: wajib inquiry dulu, `amount` harus cocok dengan hasil inquiry

### `product_tier_prices`
| Kolom            | Tipe        | Constraint              | Keterangan                              |
|------------------|-------------|-------------------------|-----------------------------------------|
| id               | bigserial   | PK                      |                                         |
| product_id       | bigint      | FK → products.id        |                                         |
| user_tier_id     | bigint      | FK → user_tiers.id      |                                         |
| sell_price_cents | bigint      | NOT NULL, CHECK > 0     | Harga jual untuk tier ini               |
| created_at       | timestamptz | NOT NULL                |                                         |
| updated_at       | timestamptz | NOT NULL                |                                         |

**Unique:** `(product_id, user_tier_id)`

---

## Domain: Inquiry

### `inquiries`
| Kolom             | Tipe         | Constraint         | Keterangan                                                    |
|-------------------|--------------|--------------------|---------------------------------------------------------------|
| id                | bigserial    | PK                 |                                                               |
| user_id           | bigint       | FK → users.id      |                                                               |
| product_id        | bigint       | FK → products.id   | Hanya produk `postpaid`                                       |
| customer_number   | varchar(50)  | NOT NULL           | Nomor pelanggan (meter ID, nomor HP, dsb.)                    |
| amount_cents      | bigint       | NOT NULL           | Nominal tagihan hasil inquiry dari provider                    |
| admin_fee_cents   | bigint       | NOT NULL DEFAULT 0 |                                                               |
| inquiry_ref       | varchar(100) | NULLABLE           | Referensi dari provider                                       |
| provider_response | jsonb        | NULLABLE           | Response mentah provider                                      |
| status            | varchar(20)  | NOT NULL           | `pending`, `success`, `failed`, `expired`                     |
| expires_at        | timestamptz  | NOT NULL           | Inquiry kedaluwarsa jika tidak langsung dibayar               |
| created_at        | timestamptz  | NOT NULL           |                                                               |

**Index:** `(user_id, status, expires_at)`

---

## Domain: Transaction

### `transactions` *(tabel dipartisi per bulan)*
| Kolom                | Tipe         | Constraint               | Keterangan                                                |
|----------------------|--------------|--------------------------|-----------------------------------------------------------|
| id                   | bigserial    | PK (composite)           |                                                           |
| user_id              | bigint       | FK → users.id            |                                                           |
| product_id           | bigint       | FK → products.id         |                                                           |
| inquiry_id           | bigint       | NULLABLE                 | FK → inquiries.id untuk produk postpaid                   |
| supplier_id          | bigint       | FK → providers.id        | Supplier yang akhirnya digunakan (ADR-005)                |
| original_supplier_id | bigint       | NULLABLE                 | Supplier preferred sebelum failover (ADR-005)             |
| is_failover          | boolean      | NOT NULL DEFAULT false   | true jika berpindah dari supplier utama (ADR-005)         |
| customer_number      | varchar(50)  | NOT NULL                 |                                                           |
| amount_cents         | bigint       | NOT NULL                 | Nominal transaksi                                         |
| sell_price_cents     | bigint       | NOT NULL                 | Harga jual yang dikenakan ke user                         |
| status               | varchar(20)  | NOT NULL DEFAULT 'pending'| `pending`, `processing`, `success`, `failed`, `refunded` |
| idempotency_key      | varchar(100) | NOT NULL                 |                                                           |
| provider_ref         | varchar(100) | NULLABLE                 | Referensi dari provider PPOB                              |
| provider_response    | jsonb        | NULLABLE                 | Response mentah terakhir dari provider                    |
| failure_reason       | varchar(255) | NULLABLE                 |                                                           |
| partner_id           | bigint       | NULLABLE                 | FK → partners.id (null jika direct mobile user)           |
| created_at           | timestamptz  | NOT NULL                 | **Kunci partisi — tidak boleh diubah setelah INSERT**     |
| updated_at           | timestamptz  | NOT NULL                 |                                                           |

**Partisi:** `PARTITION BY RANGE (created_at)` — partisi per bulan, buat dengan `DB::statement` raw SQL.

```sql
-- Contoh migration (jalankan via DB::statement)
CREATE TABLE transactions (
    id               BIGSERIAL,
    user_id          BIGINT NOT NULL,
    product_id       BIGINT NOT NULL,
    inquiry_id       BIGINT,
    supplier_id      BIGINT,
    original_supplier_id BIGINT,
    is_failover      BOOLEAN NOT NULL DEFAULT false,
    partner_id       BIGINT,
    customer_number  VARCHAR(50) NOT NULL,
    amount_cents     BIGINT NOT NULL,
    sell_price_cents BIGINT NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'pending',
    idempotency_key  VARCHAR(100) NOT NULL,
    provider_ref     VARCHAR(100),
    provider_response JSONB,
    failure_reason   VARCHAR(255),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (created_at);

-- Seed partisi awal (buat di seeder atau migration terpisah)
CREATE TABLE transactions_2026_10 PARTITION OF transactions
    FOR VALUES FROM ('2026-10-01') TO ('2026-11-01');
CREATE TABLE transactions_2026_11 PARTITION OF transactions
    FOR VALUES FROM ('2026-11-01') TO ('2026-12-01');
-- dst...
```

**Index:** `(user_id, created_at DESC)`, `idempotency_key` (per partisi), `(status, created_at)`, `partner_id`

### `idempotency_keys`
| Kolom         | Tipe         | Constraint              | Keterangan                                     |
|---------------|--------------|-------------------------|------------------------------------------------|
| id            | bigserial    | PK                      |                                                |
| user_id       | bigint       | FK → users.id           |                                                |
| key_value     | varchar(100) | NOT NULL                |                                                |
| endpoint      | varchar(100) | NOT NULL                | Path endpoint yang menerima request            |
| response_code | smallint     | NOT NULL                | HTTP status code dari response pertama         |
| response_body | jsonb        | NOT NULL                | Body response pertama                          |
| created_at    | timestamptz  | NOT NULL                |                                                |

**Unique:** `(user_id, key_value)` — constraint utama idempotency.
**Catatan:** Buat cleanup job (hapus record > 30 hari via scheduled command).

---

## Domain: PPOB

### `processed_webhook_events`
| Kolom        | Tipe         | Constraint       | Keterangan                                                     |
|--------------|--------------|------------------|----------------------------------------------------------------|
| id           | bigserial    | PK               |                                                                |
| event_id     | varchar(100) | NOT NULL, UNIQUE | ID unik event dari provider (cegah duplikat)                   |
| source       | varchar(50)  | NOT NULL         | Nama provider: `pln`, `telkomsel`, `pdam_bandung`, dsb.        |
| event_type   | varchar(50)  | NOT NULL         | `payment_success`, `payment_failed`, dsb.                      |
| payload      | jsonb        | NOT NULL         | Payload webhook lengkap                                        |
| processed_at | timestamptz  | NOT NULL         |                                                                |
| created_at   | timestamptz  | NOT NULL         |                                                                |

**Index:** `event_id` (UNIQUE)

### `product_supplier_routes`
| Kolom        | Tipe         | Constraint              | Keterangan                                                     |
|--------------|--------------|-------------------------|----------------------------------------------------------------|
| id           | bigserial    | PK                      |                                                                |
| product_id   | bigint       | FK → products.id        | Produk yang dilayani                                           |
| provider_id  | bigint       | FK → providers.id       | Supplier yang menyediakan                                      |
| priority     | smallint     | NOT NULL DEFAULT 1      | 1 = utama, makin besar makin rendah prioritasnya (ADR-004)     |
| is_active    | boolean      | NOT NULL DEFAULT true   | Status aktif rute supplier                                     |
| created_at   | timestamptz  | NOT NULL                |                                                                |
| updated_at   | timestamptz  | NOT NULL                |                                                                |

**Unique:** `(product_id, provider_id)`  
**Index:** `(product_id, priority)`

### `partner_routing_rules`
| Kolom                 | Tipe         | Constraint                 | Keterangan                                                     |
|-----------------------|--------------|----------------------------|----------------------------------------------------------------|
| id                    | bigserial    | PK                         |                                                                |
| partner_id            | bigint       | FK → partners.id           | Mitra pemilik aturan                                           |
| category_code         | varchar(30)  | NULLABLE                   | Kode kategori produk (null = aturan global mitra) (ADR-004)    |
| preferred_provider_id | bigint       | NULLABLE, FK → providers.id| Supplier pilihan partner (null = default sistem)               |
| allow_failover        | boolean      | NOT NULL DEFAULT false     | Izinkan pindah supplier jika supplier utama down (ADR-004)     |
| failover_policy       | varchar(20)  | NOT NULL DEFAULT 'none'    | `none`, `same_category`, `any`                                 |
| created_at            | timestamptz  | NOT NULL                   |                                                                |
| updated_at            | timestamptz  | NOT NULL                   |                                                                |

**Unique:** `(partner_id, category_code)`  
**Index:** `partner_id`

### `supplier_balances`
| Kolom         | Tipe         | Constraint              | Keterangan                                                     |
|---------------|--------------|-------------------------|----------------------------------------------------------------|
| id            | bigserial    | PK                      |                                                                |
| provider_id   | bigint       | FK → providers.id       | Supplier yang dimonitor                                        |
| balance_cents | bigint       | NOT NULL                | Saldo deposit supplier dalam satuan cents (ADR-009)            |
| source        | varchar(10)  | NOT NULL DEFAULT 'manual'| `api` (auto pull) atau `manual` (input admin)                  |
| checked_at    | timestamptz  | NOT NULL                | Waktu pengecekan saldo                                         |
| raw_response  | text         | NULLABLE                | Response mentah API supplier (audit)                           |
| created_at    | timestamptz  | NOT NULL                |                                                                |
| updated_at    | timestamptz  | NOT NULL                |                                                                |

**Index:** `(provider_id, checked_at DESC)`

---

## Domain: Partner

### `partners`
| Kolom                  | Tipe         | Constraint              | Keterangan                                                    |
|------------------------|--------------|-------------------------|---------------------------------------------------------------|
| id                     | bigserial    | PK                      |                                                               |
| name                   | varchar(100) | NOT NULL                |                                                               |
| user_id                | bigint       | NULLABLE, FK → users.id | User akun wallet pemilik mitra H2H (ADR-006)                  |
| api_key                | varchar(64)  | NOT NULL, UNIQUE        | Random hex 32-byte                                            |
| secret                 | varchar(255) | NOT NULL                | Random hex 32-byte, disimpan terenkripsi (Laravel encrypt)    |
| allowed_ips            | jsonb        | NULLABLE                | Array IP whitelist, null = semua IP diizinkan                 |
| protocol               | varchar(20)  | NOT NULL DEFAULT 'json' | `json` / `otomax` / `irs` (ADR-006)                          |
| response_mode          | varchar(10)  | NOT NULL DEFAULT 'async'| `sync` / `async` — cara response ke klien (ADR-007)          |
| response_timeout_ms    | integer      | NOT NULL DEFAULT 5000   | Maks waktu tunggu untuk mode sync (ADR-007)                   |
| callback_url           | varchar(500) | NULLABLE                | URL untuk notif async setelah transaksi selesai (ADR-007)     |
| callback_secret        | varchar(255) | NULLABLE                | Secret untuk HMAC signature di callback (ADR-007)             |
| is_active              | boolean      | NOT NULL DEFAULT true   |                                                               |
| rate_limit_rpm         | integer      | NOT NULL DEFAULT 60     | Request per menit                                             |
| created_at             | timestamptz  | NOT NULL                |                                                               |
| updated_at             | timestamptz  | NOT NULL                |                                                               |

### `partner_logs`
| Kolom         | Tipe         | Constraint         | Keterangan                                   |
|---------------|--------------|--------------------|----------------------------------------------|
| id            | bigserial    | PK                 |                                              |
| partner_id    | bigint       | FK → partners.id   |                                              |
| endpoint      | varchar(100) | NOT NULL           |                                              |
| method        | varchar(10)  | NOT NULL           |                                              |
| request_body  | jsonb        | NULLABLE           |                                              |
| response_code | smallint     | NOT NULL           |                                              |
| duration_ms   | integer      | NOT NULL           |                                              |
| ip_address    | varchar(45)  | NOT NULL           |                                              |
| created_at    | timestamptz  | NOT NULL           |                                              |

**Index:** `(partner_id, created_at DESC)` — untuk audit trail

### `partner_product_prices`
| Kolom            | Tipe         | Constraint              | Keterangan                                                  |
|------------------|--------------|-------------------------|-------------------------------------------------------------|
| id               | bigserial    | PK                      |                                                             |
| partner_id       | bigint       | FK → partners.id        | Mitra H2H                                                   |
| product_id       | bigint       | FK → products.id        | Produk PPOB                                                 |
| sell_price_cents | bigint       | NOT NULL, CHECK > 0     | Harga jual flat khusus untuk mitra ini (ADR-006)            |
| is_active        | boolean      | NOT NULL DEFAULT true   |                                                             |
| created_at       | timestamptz  | NOT NULL                |                                                             |
| updated_at       | timestamptz  | NOT NULL                |                                                             |

**Unique:** `(partner_id, product_id)`  
**Index:** `partner_id`

### `webhook_deliveries`
| Kolom          | Tipe         | Constraint              | Keterangan                                                  |
|----------------|--------------|-------------------------|-------------------------------------------------------------|
| id             | bigserial    | PK                      |                                                             |
| partner_id     | bigint       | FK → partners.id        | Mitra tujuan callback                                       |
| transaction_id | bigint       | NOT NULL                | ID transaksi (raw bigint, partitioned table) (ADR-008)      |
| attempt        | smallint     | NOT NULL DEFAULT 1      | Percobaan ke-1 s/d ke-5 (ADR-008)                           |
| status         | varchar(30)  | NOT NULL DEFAULT 'pending'| `pending`, `delivered`, `failed`, `failed_permanent`        |
| callback_url   | varchar(500) | NOT NULL                | Endpoint URL webhook milik mitra                            |
| response_code  | smallint     | NULLABLE                | Status kode HTTP response dari mitra                        |
| response_body  | text         | NULLABLE                | Body response yang dikembalikan mitra                       |
| next_retry_at  | timestamptz  | NULLABLE                | Jadwal kirim ulang (exponential backoff) (ADR-008)          |
| delivered_at   | timestamptz  | NULLABLE                | Timestamp ketika webhook berhasil diterima (2xx)            |
| created_at     | timestamptz  | NOT NULL                |                                                             |
| updated_at     | timestamptz  | NOT NULL                |                                                             |

**Index:** `(status, next_retry_at)`, `(partner_id, transaction_id)`

---

## Domain: Monitoring

### `reconciliation_reports`
| Kolom                       | Tipe         | Constraint              | Keterangan                                                  |
|-----------------------------|--------------|-------------------------|-------------------------------------------------------------|
| id                          | bigserial    | PK                      |                                                             |
| provider_id                 | bigint       | FK → providers.id       | Supplier yang direkonsiliasi                                |
| report_date                 | date         | NOT NULL                | Tanggal cut-off rekonsiliasi (ADR-010)                      |
| status                      | varchar(20)  | NOT NULL DEFAULT 'running'| `running`, `clean`, `discrepancy`, `error`                 |
| total_transactions_local    | integer      | NOT NULL DEFAULT 0      | Total transaksi sukses di sistem lokal                      |
| total_transactions_supplier | integer      | NOT NULL DEFAULT 0      | Total transaksi menurut laporan supplier                    |
| discrepancy_count           | integer      | NOT NULL DEFAULT 0      | Jumlah transaksi yang tidak cocok                           |
| discrepancy_amount_cents    | bigint       | NOT NULL DEFAULT 0      | Total nilai nominal selisih (cents)                         |
| notes                       | text         | NULLABLE                | Catatan rekonsiliasi                                        |
| completed_at                | timestamptz  | NULLABLE                | Waktu proses rekonsiliasi selesai                           |
| created_at                  | timestamptz  | NOT NULL                |                                                             |
| updated_at                  | timestamptz  | NOT NULL                |                                                             |

**Unique:** `(provider_id, report_date)`  
**Index:** `report_date`

### `reconciliation_discrepancies`
| Kolom                    | Tipe         | Constraint                      | Keterangan                                                  |
|--------------------------|--------------|---------------------------------|-------------------------------------------------------------|
| id                       | bigserial    | PK                              |                                                             |
| reconciliation_report_id | bigint       | FK → reconciliation_reports.id  | Laporan rekonsiliasi induk                                  |
| transaction_id           | bigint       | NULLABLE                        | ID transaksi lokal (null jika ada di supplier saja)         |
| supplier_ref             | varchar(100) | NULLABLE                        | ID transaksi supplier (null jika ada di lokal saja)         |
| discrepancy_type         | varchar(30)  | NOT NULL                        | `missing_local`, `missing_supplier`, `amount_mismatch`, `status_mismatch` |
| local_amount_cents       | bigint       | NULLABLE                        | Nominal transaksi di sistem lokal                           |
| supplier_amount_cents    | bigint       | NULLABLE                        | Nominal transaksi di laporan supplier                       |
| local_status             | varchar(20)  | NULLABLE                        | Status di sistem lokal                                      |
| supplier_status          | varchar(20)  | NULLABLE                        | Status di sistem supplier                                   |
| resolution               | varchar(20)  | NOT NULL DEFAULT 'unresolved'   | `unresolved`, `resolved_manual`, `resolved_auto`, `ignored` |
| resolution_notes         | text         | NULLABLE                        | Catatan tindakan penyelesaian                               |
| created_at               | timestamptz  | NOT NULL                        |                                                             |
| updated_at               | timestamptz  | NOT NULL                        |                                                             |

**Index:** `reconciliation_report_id`, `resolution`

---

## Relasi Antar Domain (Ringkasan)

```
user_tiers ──< users >── wallets ──< wallet_mutations
                 │          │
                 │          └──< topup_requests
                 ├──< otp_codes
                 ├──< pin_verification_tokens
                 ├──< partners (user_id) ──< partner_logs
                 │         │             ──< partner_routing_rules >── providers (preferred)
                 │         │             ──< partner_product_prices >── products
                 │         │             ──< webhook_deliveries
                 │         │
                 │         └──< transactions >── inquiries
                 │                   │       └── providers (supplier_id / original_supplier_id)
                 │                   └── partners (partner_id)
                 ├──< inquiries >── products >── product_categories
                 │                     │     └── providers ──< supplier_balances
                 │                     │            │        ──< reconciliation_reports ──< reconciliation_discrepancies
                 │                     ├──< product_supplier_routes >── providers
                 │                     └──< product_tier_prices >── user_tiers
                 └──< idempotency_keys

processed_webhook_events (standalone)
```
