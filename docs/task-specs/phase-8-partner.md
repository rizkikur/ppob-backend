# Task Spec: Phase 8 — Partner / Open API Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (Domain: Partner), `docs/openapi.yaml` (endpoint /partner/*), `docs/security-design.md` (section 5, 11), `docs/architecture-decisions.md` (ADR-006, ADR-007), `docs/dev-roadmap.md` (Phase 8).
>
> **Selesai = kode + test lulus + update changelog & dev-roadmap.md + push branch**

---

## Konteks

Phase 8 mengimplementasikan Open API untuk mitra bisnis (B2B / Partner):
1. **Autentikasi Partner (Security Design Section 5)**:
   - Header `X-Api-Key`: Identifikasi partner.
   - Header `X-Timestamp`: Unix timestamp (toleransi 300 detik).
   - Header `X-Signature`: HMAC-SHA256(`partner.secret`, `METHOD\nPATH\nTIMESTAMP\nSHA256(raw_body)`).
   - Verifikasi constant-time via `hash_equals`.
2. **Proteksi Akses**:
   - IP Whitelist check (`allowed_ips`) → 403 `PARTNER_IP_BLOCKED`.
   - Rate limiting per partner (`rate_limit_rpm` di Redis/Cache) → 429 `PARTNER_RATE_LIMITED`.
   - Audit trail setiap request partner dicatat ke tabel `partner_logs`.
3. **Harga Khusus Partner (ADR-006)**:
   - Tabel `partner_product_prices`: Harga jual khusus per partner per produk.
   - Fallback ke harga base modal + admin fee jika tidak ada penyesuaian khusus.
4. **Open API Endpoints**:
   - `GET /partner/balance`: Cek saldo deposit mitra.
   - `GET /partner/products`: Katalog produk dengan harga jual partner.
   - `POST /partner/transactions`: Pembelian produk PPOB atas nama pelanggan akhir mitra.
   - `GET /partner/transactions/{ref}`: Cek status transaksi via ID / ref transaksi.

---

## File yang Dibuat & Dikelola

```
app/Domain/Partner/
├── Models/
│   ├── Partner.php
│   ├── PartnerLog.php
│   └── PartnerProductPrice.php
├── Services/
│   └── PartnerAuthService.php
├── Http/
│   ├── Controllers/
│   │   └── PartnerController.php
│   ├── Middleware/
│   │   └── PartnerAuthMiddleware.php
│   ├── Requests/
│   │   └── CreatePartnerTransactionRequest.php
│   └── Resources/
│       ├── PartnerProductResource.php
│       └── PartnerTransactionResource.php
routes/
└── partner.php
database/migrations/
└── 2026_10_06_000001_add_user_id_to_partners_table.php
tests/Feature/Partner/
├── PartnerAuthTest.php
├── PartnerRateLimitTest.php
├── PartnerIpWhitelistTest.php
└── PartnerTransactionTest.php
```

---

## Rincian Item Pekerjaan

### Item 8.1 — Model: `Partner`, `PartnerLog`, `PartnerProductPrice`
- `Partner`: Relasi ke `user()`, `logs()`, `productPrices()`. Helpers dekripsi secret, saldo, pricing per produk.
- `PartnerLog`: Audit log request (endpoint, method, request_body, response_code, duration_ms, ip_address).
- `PartnerProductPrice`: Model harga jual per partner per produk.

### Item 8.2 — Service: `PartnerAuthService`
- Validasi API key aktif.
- Pengecekan IP whitelist.
- Pengecekan toleransi timestamp 300s.
- Penegakan rate limit RPM.
- Verifikasi HMAC-SHA256 signature secara constant-time.

### Item 8.3 — Middleware: `PartnerAuthMiddleware`
- Menjalankan `PartnerAuthService::validate()`.
- Menyetel attribute `partner` dan mengautentikasi representasi `User` partner.
- Mencatat log request dan response ke `partner_logs`.

### Item 8.4 — Controller: `PartnerController`
- `GET /partner/balance`
- `GET /partner/products`
- `POST /partner/transactions`
- `GET /partner/transactions/{partnerRef}`

### Item 8.5 – 8.7 — Feature Tests
- 8.5: `PartnerAuthTest` (invalid API key, invalid signature, stale timestamp, inactive partner).
- 8.6: `PartnerRateLimitTest` (RPM limit 429).
- 8.7: `PartnerIpWhitelistTest` (IP blocked 403).
- End-to-end partner transaction, idempotency, custom pricing, and audit log.
