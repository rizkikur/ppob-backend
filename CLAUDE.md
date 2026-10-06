# PPOB Backend — Context untuk AI Agent

## Ringkasan Project
Backend PPOB (pulsa, token listrik, tagihan, dst) + wallet, dibangun Laravel 11 + PostgreSQL + Redis + Horizon. Target skala: 1 juta transaksi/tahun, peak 50–100 RPS. Konsumen: mobile app + partner Open API (JSON) + partner H2H (OTOMAX, IRS nanti).

## Dokumen Acuan (baca sebelum mengerjakan task apapun)
@docs/openapi.yaml
@docs/ERD.md
@docs/security-design.md
@docs/backend-skeleton-structure.md
@docs/dev-roadmap.md
@docs/architecture-decisions.md

Kalau ada perbedaan antara dokumen di atas dan kode yang sudah ada, dokumen yang benar — perbarui dokumen dulu baru ubah kode, jangan diam-diam menyimpang.

## Arsitektur
Modular monolith, domain-oriented di `app/Domain/{DomainName}/`. Setiap domain punya `Models/`, `Services/`, `Http/Controllers`, `Http/Requests`, `Http/Resources`. Detail lengkap di `docs/backend-skeleton-structure.md`. Jangan taruh Model/Controller baru di `app/Models` atau `app/Http/Controllers` default Laravel — semua masuk ke domain masing-masing di bawah `app/Domain/`.

## Aturan Keras (tidak boleh dilanggar, apapun alasannya)

1. **Uang selalu `Money` value object** (`app/Domain/Shared/ValueObjects/Money.php`), tidak pernah float/decimal mengambang. Kolom database untuk uang selalu `bigint` cents, di-cast pakai `MoneyCast`.
2. **Semua perubahan saldo wajib lewat `WalletService`** — tidak ada controller/job lain yang boleh `Wallet::update()` langsung. Method di `WalletService` wajib pakai `SELECT ... FOR UPDATE` (row lock) + insert ke `wallet_mutations` dalam satu DB transaction.
3. **`wallet_mutations` append-only** — jangan pernah generate migration atau kode yang UPDATE/DELETE baris di tabel ini. Sudah ada trigger di level database yang menolak ini, tapi jangan coba mengakalinya.
4. **Endpoint yang ubah saldo/stok wajib validasi `Idempotency-Key`** — cek unique constraint `(user_id, idempotency_key)`, kalau duplikat kembalikan hasil transaksi pertama (response `409`), jangan diproses ulang.
5. **PIN tidak pernah dikirim ke endpoint transaksi.** Alurnya: `POST /security/pin/challenge` → `POST /security/pin/verify` (baru di sini PIN dicek) → terbitkan `pin_verification_token` sekali pakai → endpoint transaksi terima token ini, bukan PIN. Detail di `docs/security-design.md` bagian 2.
6. **Produk prabayar (`product_type=prepaid`): harga selalu dari katalog server-side.** `amount` dari klien diabaikan untuk produk ini. Produk pascabayar (`product_type=postpaid`): wajib inquiry dulu, `amount` divalidasi sama dengan hasil inquiry.
7. **Webhook (`/ppob/callback`, `/wallet/callback`) endpoint terpisah dari API publik**, validasi berurutan: mTLS → `X-Signature` (HMAC-SHA256) → `X-Timestamp` (tolak jika selisih >300 detik) → cek `event_id` di `processed_webhook_events` (tolak duplikat dengan `409`, jangan diproses ulang).
8. **Response envelope selalu lewat `ApiResponse`/`ApiController`** (`app/Domain/Shared/Http/`) — jangan return `response()->json()` mentah di controller manapun.
9. **`error_code` di response error harus dari daftar yang sudah distandarkan** di `docs/security-design.md` bagian 11 — jangan bikin string error baru sembarangan.
10. **Tabel `transactions` di-partition per bulan** — migration untuk tabel ini pakai raw SQL (`DB::statement`), bukan Schema Builder biasa. Lihat contoh di `docs/ERD.md`. Untuk ALTER TABLE transactions, selalu pakai `DB::statement`.

## Keputusan Arsitektur (ADR) — Ringkasan Wajib Dibaca

Detail lengkap ada di `docs/architecture-decisions.md`. Ringkasan di bawah adalah yang harus selalu diingat saat coding:

### ADR-002: Multi-Supplier via Named Queue
- Setiap `Provider` di DB punya `queue_name` — job dikirim ke queue supplier yang sesuai, bukan ke `default`.
- Konfigurasi worker per supplier ada di `config/horizon.php`.
- Field di `providers`: `queue_name`, `max_workers`, `rate_limit_per_minute`, `timeout_seconds`, `priority`, `supports_balance_api`, `balance_alert_threshold_cents`.

### ADR-003: Circuit Breaker Per-Supplier
- State disimpan di Redis: `circuit:{supplier_code}:state` — nilai: `closed` | `open` | `half-open`.
- Saat circuit OPEN, transaksi langsung return error `PROVIDER_UNAVAILABLE` tanpa memanggil supplier.
- Service: `App\Domain\Ppob\Services\CircuitBreakerService`.

### ADR-004: Failover Routing (Opsional Per-Klien)
- Failover hanya boleh terjadi **sebelum** job pertama di-dispatch, di `SupplierRoutingService::resolve()`.
- Dikontrol per klien via tabel `partner_routing_rules` + `product_supplier_routes`.
- Klien tanpa aturan → default: `allow_failover = false`.

### ADR-005: Retry vs Failover (ATURAN KERAS)
- Retry = kirim ulang ke **supplier yang sama**.
- Failover = pindah ke supplier lain **sebelum** request pertama berhasil.
- Sekali job di-dispatch ke supplier X, **tidak boleh pindah** ke supplier lain di tengah jalan.
- Field di `transactions`: `supplier_id`, `original_supplier_id`, `is_failover`.

### ADR-006: Multi-Protocol API
- REST/JSON untuk mobile + partner JSON: `/api/v1/*`.
- H2H OTOMAX: `/h2h/otomax/*` — stub saja untuk sekarang (return 501).
- H2H IRS: `/h2h/irs/*` — stub saja untuk sekarang (return 501).
- Admin panel: `/admin/*` dengan middleware `role:admin`.
- Field di `users`: `role` (user | admin | super_admin).
- Field di `partners`: `protocol` (json | otomax | irs).
- Tabel `partner_product_prices` untuk harga flat per partner (terpisah dari `product_tier_prices`).

### ADR-007: Per-Klien Response Mode
- Field di `partners`: `response_mode` (sync|async), `response_timeout_ms`, `callback_url`, `callback_secret`.
- Mode sync: tunggu sampai `response_timeout_ms`, kalau belum selesai return 202 + status pending.
- Mode async: langsung return 202 + transaction_id, kirim callback setelah selesai.

### ADR-008: Webhook Retry ke Klien (Exponential Backoff)
- Max 5 attempt: langsung → +1m → +5m → +30m → +2j → alert admin.
- Tracking di tabel `webhook_deliveries`.
- Job: `App\Domain\Partner\Jobs\DeliverWebhookJob`.

### ADR-009: Supplier Balance Monitoring
- Tabel `supplier_balances` untuk histori saldo deposit di supplier.
- Command `supplier:check-balance` scheduled setiap 15 menit.
- Circuit breaker otomatis OPEN jika saldo terdeteksi 0.

### ADR-010: Rekonsiliasi Harian
- Command `reconcile:daily {supplier}` — scheduled harian.
- Ketidakcocokan dicatat di `reconciliation_discrepancies`.
- Ringkasan di `reconciliation_reports`.

## Encoding & Format File PHP
- **WAJIB**: semua file PHP harus **UTF-8 without BOM**.
- Pastikan tidak ada karakter `0xEF 0xBB 0xBF` di awal file. BOM menyebabkan `namespace` error.
- Jika membuat file baru, pastikan editor/tool menyimpan tanpa BOM.

## Commands
```bash
./vendor/bin/sail up -d          # jalankan environment (Postgres+Redis+app)
php artisan migrate              # jalankan migration
php artisan test                 # atau: ./vendor/bin/pest
php artisan horizon              # jalankan queue worker (wajib untuk test job async)
./vendor/bin/phpstan analyse     # static analysis
```

## Testing — wajib, bukan opsional
Setiap fitur yang menyentuh saldo/stok WAJIB punya Feature test untuk:
- **Idempotency**: kirim request sama 2x dengan `Idempotency-Key` sama, assert hasil kedua = hasil pertama (bukan transaksi baru)
- **Race condition**: dua request bersamaan ke wallet yang sama tidak boleh membuat saldo minus atau ganda
- **Validasi prepaid/postpaid**: inquiry ditolak untuk SKU prepaid, amount mismatch ditolak untuk SKU postpaid

Jangan tandai task selesai kalau test belum ditulis dan lulus.

## Alur Kerja
Satu sesi fokus ke satu domain sesuai urutan di `docs/dev-roadmap.md`. Setiap selesai satu item, update kolom **Status** di `docs/dev-roadmap.md`.

## Yang TIDAK boleh dilakukan
- Jangan commit langsung ke `main` — selalu lewat branch + PR, terutama untuk perubahan di `Wallet/`, `Transaction/`, dan migration.
- Jangan akses atau baca file `.env` production dalam sesi manapun.
- Jangan generate kode yang mem-bypass validasi `Idempotency-Key` atau `pin_verification_token` "untuk mempercepat testing".
- Jangan taruh file PHP dengan BOM — selalu simpan sebagai UTF-8 without BOM.

