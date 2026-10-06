# Architecture Decisions — PPOB Backend

Dokumen ini merekam semua keputusan arsitektur yang disepakati selama sesi diskusi.
Setiap keputusan yang mengubah dokumen ini **harus didiskusikan dan dicatat di sini dulu**
sebelum implementasi dimulai.

Format tiap keputusan:
- **Konteks** — masalah atau kebutuhan yang melatarbelakangi
- **Keputusan** — apa yang disepakati
- **Konsekuensi** — impak ke desain/kode
- **Status** — `Disepakati` | `Draft` | `Ditinjau Ulang`

**Terakhir diperbarui:** 2026-10-05 — Semua ADR Draft sudah ditutup.

---

## ADR-001 — Arsitektur: Modular Monolith

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Pilihan antara microservices, monolith, atau modular monolith untuk backend PPOB dengan
target 1 juta transaksi/tahun dan peak 50–100 RPS.

**Keputusan:**
Gunakan **modular monolith** — satu aplikasi Laravel, kode diorganisir per domain bisnis
di `app/Domain/{DomainName}/`. Domain tidak boleh saling import class langsung,
komunikasi antar domain via interface atau event.

**Konsekuensi:**
- Lebih mudah di-deploy dan di-debug dibanding microservices
- Jika suatu saat butuh pecah jadi service, batas domain sudah jelas
- Semua aturan keras di `CLAUDE.md` berlaku untuk menjaga isolasi domain

---

## ADR-002 — Multi-Supplier dengan Worker Control Per-Supplier

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Sistem perlu terhubung ke banyak supplier PPOB (Telkomsel, PLN, PDAM, dll).
Setiap supplier punya kapasitas berbeda — supplier A bisa handle 100 request/menit,
supplier B hanya 20 request/menit.

**Keputusan:**
Gunakan **named queue per supplier** di Laravel Horizon. Jumlah worker per queue
dikonfigurasi di `config/horizon.php` dan bisa berbeda-beda antar supplier:

```php
'supplier-telkomsel' => ['queue' => ['supplier_telkomsel'], 'processes' => 100],
'supplier-pln'       => ['queue' => ['supplier_pln'],       'processes' => 20],
```

Setiap `Provider` di database punya field:
- `queue_name` — nama queue Horizon yang digunakan
- `max_workers` — jumlah worker (untuk dokumentasi & sync ke horizon config)
- `rate_limit_per_minute` — maks request per menit ke API supplier ini
- `timeout_seconds` — timeout HTTP call ke supplier
- `priority` — urutan preferensi saat ada multiple supplier untuk satu produk

**Konsekuensi:**
- Perlu tambah kolom-kolom di atas ke tabel `providers` (update ERD.md)
- Job transaksi di-dispatch ke queue supplier yang sesuai, bukan ke queue default
- Horizon config harus diperbarui setiap ada supplier baru

---

## ADR-003 — Circuit Breaker Per-Supplier

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Tanpa circuit breaker, jika supplier A down, semua transaksi antri dan retry terus
sampai queue penuh → delay semua transaksi lain → cascading failure.

**Keputusan:**
Implementasikan **circuit breaker pattern** per supplier menggunakan Redis counter:

```
[CLOSED]    → request normal, pantau failure rate
[OPEN]      → reject langsung (fail fast), tidak kirim request ke supplier
[HALF-OPEN] → kirim 1 request test setelah cooldown
```

Threshold dan cooldown dikonfigurasi per supplier di database atau `config/ppob.php`.
State circuit breaker disimpan di Redis (`circuit:{supplier_code}:state`).

Service baru yang perlu dibuat: `App\Domain\Ppob\Services\CircuitBreakerService`

**Konsekuensi:**
- Saat circuit OPEN, transaksi langsung return `PROVIDER_UNAVAILABLE` tanpa nunggu timeout
- Perlu monitoring dashboard untuk melihat state circuit per supplier
- Jika circuit OPEN dan `allow_failover = true` → lanjut ke ADR-004

---

## ADR-004 — Failover Routing Antar Supplier (Per-Klien, Per-Kategori)

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Beberapa produk bisa dilayani oleh lebih dari satu supplier. Saat supplier utama down,
sistem bisa otomatis pindah ke supplier lain. **Namun ini tidak selalu diinginkan.**

Masalah spesifik: **SN (Serial Number)** yang diterbitkan oleh supplier berbeda
akan berbeda. Klien yang sudah expect SN dari supplier A akan komplain
jika tiba-tiba dapat SN dari supplier B.

**Keputusan:**
Failover bersifat **opsional dan dikonfigurasi per-klien per-kategori produk**
via tabel baru `partner_routing_rules`:

| Kolom | Tipe | Keterangan |
|---|---|---|
| partner_id | bigint | FK ke partners |
| category_code | varchar(30) | Nullable = berlaku untuk semua kategori |
| preferred_supplier_id | bigint | Nullable = bebas pilih supplier mana saja |
| allow_failover | boolean | true = boleh pindah supplier jika utama down |
| failover_policy | varchar(20) | `none` / `same_category` / `any` |

**Nilai `failover_policy`:**
- `none` — kalau supplier utama gagal, langsung return gagal ke klien (tidak pindah)
- `same_category` — failover ke supplier lain yang support kategori produk yang sama
- `any` — failover ke supplier manapun yang tersedia

**Lookup rule:** ambil aturan yang paling spesifik dulu (category_code match),
fallback ke aturan global (category_code IS NULL).

**Konsekuensi:**
- Perlu tabel baru `partner_routing_rules` (update ERD.md)
- Perlu tabel baru `product_supplier_routes` — daftar supplier yang bisa melayani produk tertentu beserta prioritasnya
- Perlu service baru `App\Domain\Ppob\Services\SupplierRoutingService`
- Klien tanpa aturan → default: `allow_failover = false` (aman, tidak berpindah-pindah)

---

## ADR-005 — Perbedaan Retry vs Failover (Aturan Keras)

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Harus ada pembedaan jelas antara "retry ke supplier yang sama" dan "failover ke supplier lain"
untuk mencegah double-processing.

**Keputusan:**
```
Retry   = kirim ulang ke SUPPLIER YANG SAMA setelah timeout/error sementara
Failover = pindah ke SUPPLIER LAIN sebelum request pertama berhasil

Aturan keras:
Sekali transaksi DIKIRIM ke supplier X (job sudah dispatch),
retry untuk transaksi yang SAMA harus tetap ke supplier X.
TIDAK BOLEH pindah ke supplier lain di tengah jalan.
```

Alasannya: supplier X mungkin sudah memproses dan SN sudah diterbitkan,
tapi response yang hilang. Pindah ke supplier Y bisa menyebabkan **double SN**
untuk nomor pelanggan yang sama.

**Failover** hanya boleh terjadi **sebelum job pertama di-dispatch**,
di level `SupplierRoutingService.resolve()` — bukan di dalam job.

**Konsekuensi:**
- Tabel `transactions` perlu field tambahan:
  - `supplier_id` — supplier yang akhirnya digunakan
  - `original_supplier_id` — supplier yang seharusnya dipakai (preferred)
  - `is_failover` — boolean, apakah ini hasil failover?
- `ProcessTransactionJob` retry hanya ke supplier yang sama (`$this->transaction->supplier_id`)

---

## ADR-006 — Format API: Multi-Protocol

**Tanggal:** 2026-10-01 | **Diperbarui:** 2026-10-05
**Status:** Disepakati

**Konteks:**
Sistem perlu mendukung lebih dari satu format API karena konsumen yang berbeda:
- Mobile app (langsung ke user akhir)
- Klien H2H (mitra bisnis yang punya sistem sendiri)
- Klien yang sudah pakai software PPOB standar (OTOMAX, IRS)

**Keputusan:**
Implementasikan **Protocol Adapter Layer** sekarang (interface/stub kosong),
implementasi OTOMAX dan IRS di phase tersendiri setelah core JSON stabil:

```
Core Business Logic (TransactionService, domain rules)
        │
        ├── REST/JSON OpenAPI  → /api/v1/*          (Mobile + Partner JSON) ← Phase 1-8
        ├── Admin Panel        → /admin/*            (Same app, middleware berbeda)
        ├── OTOMAX Adapter     → /h2h/otomax/*      (Stub sekarang, implementasi nanti)
        └── IRS Adapter        → /h2h/irs/*         (Stub sekarang, implementasi nanti)
```

Setiap adapter bertanggung jawab untuk:
1. Parse format request protokol spesifik
2. Konversi ke format internal domain
3. Panggil service yang sama (tidak duplikasi business logic)
4. Konversi response ke format protokol

**Keputusan tambahan:**
- **Admin panel**: route `/admin/*` di Laravel app yang sama, middleware `auth.admin` terpisah
- **Partner pricing**: tabel `partner_product_prices` terpisah dari `product_tier_prices`
  (flat markup per partner per produk, independent dari user tier)

**Konsekuensi:**
- Field `protocol` di tabel `partners`: `json` / `otomax` / `irs` (default: `json`)
- Field `role` di tabel `users`: `user` / `admin` / `super_admin` (untuk admin panel)
- Route group: `/admin/*` dengan middleware `auth.sanctum` + `role:admin`
- Route group: `/h2h/otomax/*` dan `/h2h/irs/*` (stub controller, 501 Not Implemented)
- Tabel baru: `partner_product_prices` (`partner_id`, `product_id`, `sell_price_cents`)

---

## ADR-007 — Per-Klien Response Timeout & Mode Async/Sync

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Klien H2H punya kebutuhan berbeda soal waktu tunggu response:
- Klien A mau response dalam 5 detik (sync mode) → kalau belum selesai, return pending
- Klien B tidak masalah async → server proses di background, notif via callback

**Keputusan:**
Konfigurasi per-klien di tabel `partners`:

| Field | Tipe | Keterangan |
|---|---|---|
| `response_mode` | enum | `sync` / `async` |
| `response_timeout_ms` | integer | Maks waktu tunggu untuk mode sync (default: 5000ms) |
| `callback_url` | varchar | URL untuk mengirim notif setelah transaksi selesai (mode async) |
| `callback_secret` | varchar | Secret untuk HMAC signature di notif callback |

**Flow mode sync:**
```
POST /transactions → proses (atau tunggu job) → return result atau 408 Timeout
```
Jika timeout: return `202 Accepted` dengan `status: pending`, klien polling
`GET /transactions/{id}`.

**Flow mode async:**
```
POST /transactions → langsung return 202 + transaction_id
Job selesai → kirim POST ke callback_url klien (dengan retry, lihat ADR-008)
```

**Konsekuensi:**
- Perlu tambah field-field di atas ke tabel `partners` (update ERD.md)
- Perlu `WebhookDeliveryJob` untuk kirim notif ke callback URL klien

---

## ADR-008 — Webhook Retry ke Klien (Exponential Backoff)

**Tanggal:** 2026-10-01
**Status:** Disepakati

**Konteks:**
Jika callback URL klien sedang down saat transaksi selesai, webhook gagal.
Tanpa retry, klien tidak pernah tahu hasil transaksi.

**Keputusan:**
Implementasikan retry dengan **exponential backoff**:

| Attempt | Delay |
|---------|-------|
| 1 | Langsung setelah transaksi selesai |
| 2 | +1 menit |
| 3 | +5 menit |
| 4 | +30 menit |
| 5 | +2 jam |
| Setelah attempt 5 gagal | Alert ke admin, tandai `webhook_status: failed_permanent` |

Tracking di tabel baru `webhook_deliveries`:
- `partner_id`, `transaction_id`, `attempt`, `status`, `response_code`,
  `next_retry_at`, `delivered_at`

**Konsekuensi:**
- Perlu tabel baru `webhook_deliveries` (update ERD.md)
- Perlu job `DeliverWebhookJob` yang dispatchable dengan delay
- Perlu scheduled command untuk alert jika ada webhook yang `failed_permanent`

---

## ADR-009 — Supplier Balance Monitoring

**Tanggal:** 2026-10-01 | **Diperbarui:** 2026-10-05
**Status:** Disepakati

**Konteks:**
Di model bisnis PPOB, operator (kita) biasanya punya **deposit/saldo di supplier**.
Kalau saldo di supplier habis, semua transaksi via supplier tersebut akan gagal.

**Keputusan:**
- **Automated pull via API supplier** jika supplier support endpoint cek saldo
- **Fallback ke manual entry** oleh admin jika supplier tidak punya API saldo
- Field `supports_balance_api` di tabel `providers` menentukan mana yang otomatis
- Alert otomatis jika saldo < `balance_alert_threshold` (dikonfigurasi per supplier)
- Circuit breaker otomatis OPEN jika saldo terdeteksi 0

**Konsekuensi:**
- Tabel `supplier_balances`: `provider_id`, `balance_cents`, `source` (`api`/`manual`), `checked_at`
- Field tambahan di `providers`: `supports_balance_api`, `balance_alert_threshold_cents`
- Scheduled command `supplier:check-balance` (run setiap 15 menit untuk supplier dengan API)

---

## ADR-010 — Rekonsiliasi Harian

**Tanggal:** 2026-10-01 | **Diperbarui:** 2026-10-05
**Status:** Disepakati

**Konteks:**
Perlu memastikan data transaksi di sistem kita cocok dengan laporan dari supplier.
Ketidakcocokan bisa terjadi karena: network timeout, bug, atau fraud.

**Keputusan:**
- **Rekonsiliasi otomatis harian** via `php artisan reconcile:daily {supplier}` (scheduled)
- **Alert realtime** jika selisih terdeteksi dalam 1 jam (via job monitoring)
- Flow:
  1. Pull laporan transaksi dari API supplier (atau parse file laporan)
  2. Compare dengan transaksi `success` di database untuk tanggal yang sama
  3. Flag ketidakcocokan ke tabel `reconciliation_discrepancies`
  4. Simpan ringkasan di tabel `reconciliation_reports`
  5. Kirim email/notifikasi ke admin
  6. Job terpisah berjalan setiap jam untuk deteksi awal selisih kritis

**Konsekuensi:**
- Perlu tabel `reconciliation_reports` dan `reconciliation_discrepancies`
- Perlu artisan command `reconcile:daily`
- Perlu scheduled job `ReconciliationAlertJob` (run setiap jam)

---

## Ringkasan Perubahan Desain vs Skeleton Awal

Berikut daftar lengkap semua tambahan yang sudah disepakati:

### Tabel Baru (10 tabel)
| Tabel | ADR | Keterangan |
|---|---|---|
| `partner_routing_rules` | ADR-004 | Aturan failover per klien per kategori |
| `product_supplier_routes` | ADR-004 | Daftar supplier per produk dengan prioritas |
| `partner_product_prices` | ADR-006 | Harga jual per partner per produk (flat markup) |
| `webhook_deliveries` | ADR-008 | Tracking pengiriman callback ke klien dengan retry |
| `supplier_balances` | ADR-009 | Monitoring saldo deposit di masing-masing supplier |
| `reconciliation_reports` | ADR-010 | Ringkasan hasil rekonsiliasi harian per supplier |
| `reconciliation_discrepancies` | ADR-010 | Detail ketidakcocokan yang ditemukan saat rekonsiliasi |

### Kolom Tambahan di Tabel yang Ada
| Tabel | Kolom Baru | ADR |
|---|---|---|
| `users` | `role` (user/admin/super_admin) | ADR-006 |
| `providers` | `queue_name`, `max_workers`, `rate_limit_per_minute`, `timeout_seconds`, `priority`, `supports_balance_api`, `balance_alert_threshold_cents` | ADR-002, ADR-009 |
| `transactions` | `supplier_id`, `original_supplier_id`, `is_failover` | ADR-005 |
| `partners` | `response_mode`, `response_timeout_ms`, `callback_url`, `callback_secret`, `protocol` | ADR-006, ADR-007 |

### Service/Class Baru
| Class | ADR | Phase |
|---|---|---|
| `CircuitBreakerService` | ADR-003 | Phase 7 (PPOB) |
| `SupplierRoutingService` | ADR-004 | Phase 7 (PPOB) |
| `DeliverWebhookJob` | ADR-008 | Phase 8 (Partner) |
| `OtomaxAdapter` (stub) | ADR-006 | Phase 9 (H2H Protocol) |
| `IrsAdapter` (stub) | ADR-006 | Phase 9 (H2H Protocol) |
| `ReconcileCommand` | ADR-010 | Phase 10 (Monitoring) |
| `ReconciliationAlertJob` | ADR-010 | Phase 10 (Monitoring) |
| `CheckSupplierBalanceCommand` | ADR-009 | Phase 10 (Monitoring) |

---

## Status Keputusan

Semua keputusan sudah disepakati per **2026-10-05**. Tidak ada ADR yang masih Draft.

| ADR | Status |
|-----|--------|
| ADR-001 s/d ADR-010 | ✅ Semua Disepakati |

- [ ] Detail format protokol OTOMAX (request/response field, auth method)
- [ ] Detail format protokol IRS
- [ ] Apakah perlu admin panel terpisah, atau cukup endpoint admin di API yang sama?
- [ ] Pricing engine untuk klien H2H: apakah pakai `partner_product_prices` terpisah
      dari `product_tier_prices`?
- [ ] Supplier balance API: apakah semua supplier support pull saldo programmatik?
- [ ] Format laporan rekonsiliasi dari masing-masing supplier
