# Changelog

Semua perubahan dan catatan rilis pada proyek PPOB Backend didokumentasikan di sini.
Format mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

---

## [Unreleased]

## [v0.9.0] - 2026-10-07
### Deployment & Scheduled Maintenance Jobs
- **Added**:
  - **Docker Production Stack**:
    - `Dockerfile`: Multi-stage PHP 8.3-FPM Alpine dengan ekstensi lengkap (`pdo_pgsql`, `pgsql`, `bcmath`, `redis`, `opcache`, `pcntl`, `posix`, `zip`, `intl`) via `install-php-extensions`.
    - `docker/php/php.ini` & `docker/php/opcache.ini`: Tuning performa produksi untuk high-throughput PPOB.
    - `docker/nginx/default.conf`: Nginx reverse proxy dengan security headers, caching, gzip, dan FastCGI forwarding ke PHP-FPM.
    - `docker/entrypoint.sh`: Otomasi permission direktori storage/cache dan proses bootstrap container.
    - `docker-compose.yml`: Orkestrasi 6 service produksi (`app`, `web`, `db` PostgreSQL 16 Alpine, `redis` Redis 7 Alpine, `queue` worker terisolasi ADR-002, dan `scheduler`).
    - `.env.docker.example` & `.dockerignore`: Konfigurasi variabel lingkungan container dan optimalisasi build context.
  - **Scheduled Maintenance Jobs**:
    - `App\Domain\Transaction\Commands\CleanupIdempotencyKeysCommand` (`ppob:cleanup:idempotency`): Pruning record `idempotency_keys` yang melewati masa retensi (default 30 hari).
    - `App\Domain\Security\Commands\CleanupExpiredTokensCommand` (`ppob:cleanup:tokens`): Pruning kode OTP dan PIN verification tokens yang kadaluarsa/sudah terpakai (default retensi 24 jam).
    - `App\Domain\Ppob\Commands\CheckProviderHealthCommand` (`ppob:health:check`): Monitoring status provider PPOB dan Circuit Breaker (ADR-003) dengan opsi `--strict` exit code.
  - **Scheduler Configuration**:
    - Didaftarkan di `routes/console.php` dengan locking `withoutOverlapping()` dan `runInBackground()`:
      - `ppob:cleanup:idempotency --days=30` setiap hari pukul 02:00.
      - `ppob:cleanup:tokens --hours=24` setiap jam.
      - `ppob:health:check` setiap 5 menit.
  - **Testing**:
    - Feature test `ScheduledCleanupTest` memverifikasi pruning idempotency key, pembersihan OTP & PIN token kadaluarsa, serta pemantauan circuit breaker provider (semua skenario sukses dan strict failure).
    - Test suite bertambah menjadi 134 tests (500 assertions), 100% lulus.

---

## [v0.8.1] - 2026-10-07
### Queue Worker & Horizon Config (ADR-002)
- **Added**:
  - Konfigurasi `config/horizon.php` dengan supervisor terisolasi per supplier sesuai kapasitas masing-masing:
    - `supervisor-telkomsel` (queue: `supplier_telkomsel`, maxProcesses: 100)
    - `supervisor-indosat` (queue: `supplier_indosat`, maxProcesses: 50)
    - `supervisor-xl` (queue: `supplier_xl`, maxProcesses: 50)
    - `supervisor-pln` (queue: `supplier_pln`, maxProcesses: 30)
    - `supervisor-pdam` (queue: `supplier_pdam`, maxProcesses: 20)
    - `supervisor-default` (queue: `default, transactions`, processes: 10)
  - Konfigurasi supervisor server produksi di `deploy/supervisor/ppob-worker.conf` siap pakai untuk Linux/VPS.
  - Pemetaan queue dan limit worker di `config/ppob.php` (`queues.suppliers`).
  - Artisan Command `php artisan ppob:queue:status` untuk inspeksi alokasi worker dan validasi konfigurasi supplier secara real-time.
  - Feature test `QueueWorkerHorizonTest`:
    - Validasi struktur file `config/horizon.php`.
    - Validasi file supervisor deployment `ppob-worker.conf`.
    - Pengujian dispatch job `ProcessTransactionJob` secara dinamis ke queue spesifik supplier (`assertPushedOn`).
    - Validasi eksekusi command `ppob:queue:status`.
  - Total test suite bertambah menjadi 130 tests (480 assertions), 100% lulus.

---

## [v0.8.0] - 2026-10-06
### Selesai: Phase 8 — Partner / Open API Domain
- **Added**:
  - Migration: `2026_10_06_000001_add_user_id_to_partners_table` menghubungkan partner ke entitas `users` untuk pengelolaan dompet saldo deposit dan integrasi idempotency.
  - Model `Partner`: Relasi ke `user()`, `logs()`, `productPrices()`, helper dekripsi AES-256 secret (`getSecretDecrypted()`), custom pricing resolver (`getPriceForProduct()`), dan wallet deposit balance accessor (`getBalance()`).
  - Model `PartnerProductPrice` (ADR-006): Skema harga flat per partner per produk dengan `MoneyCast`.
  - Model `PartnerLog`: Model pencatatan audit log request H2H/B2B (`endpoint`, `method`, `request_body`, `response_code`, `duration_ms`, `ip_address`).
  - Service `PartnerAuthService`: Validasi autentikasi API Key aktif, toleransi waktu timestamp (300 detik), IP Whitelist filtering, rate limit per partner (RPM via Cache), dan verifikasi HMAC-SHA256 signature secara constant-time (`hash_equals`).
  - Middleware `PartnerAuthMiddleware`: Interceptor request partner, mapping ke `User` context (`Auth::setUser()`), serta pencatatan audit log otomatis ke tabel `partner_logs`.
  - Service `TransactionService::createForPartner()`: Pembuatan transaksi partner dengan pendebetan saldo deposit mitra dan penerapan harga khusus ADR-006.
  - Controller `PartnerController`:
    - `GET /partner/balance` & `/api/v1/partner/balance`: Cek saldo deposit mitra.
    - `GET /partner/products` & `/api/v1/partner/products`: Katalog produk dengan harga jual khusus mitra (ADR-006).
    - `POST /partner/transactions` & `/api/v1/partner/transactions`: Transaksi PPOB partner terlindung Idempotency.
    - `GET /partner/transactions/{partnerRef}` & `/api/v1/partner/transactions/{partnerRef}`: Cek status transaksi via numeric ID atau Idempotency-Key.
  - Form Requests & Resources:
    - `CreatePartnerTransactionRequest`
    - `PartnerProductResource`
    - `PartnerTransactionResource`
  - Feature tests:
    - `PartnerAuthTest` (9 test): Validasi API key, signature HMAC, toleransi timestamp, sha256 prefix, partner nonaktif, dan audit logging.
    - `PartnerRateLimitTest` (2 test): Penegakan RPM (429 `PARTNER_RATE_LIMITED`) dan isolasi antar mitra.
    - `PartnerIpWhitelistTest` (3 test): Penolakan IP di luar whitelist (403 `PARTNER_IP_BLOCKED`), IP diizinkan, dan null wildcard.
    - `PartnerTransactionTest` (8 test): Cek saldo, harga khusus ADR-006, debit dompet, penegakan idempotency 409 conflict, saldo tidak cukup 422, dan isolasi data transaksi antar partner.
    - Total 22 test baru — suite keseluruhan kini 126 tests (446 assertions) 100% lulus.

---

## [v0.7.0] - 2026-10-06
### Selesai: Phase 7 — PPOB Integration Domain
- **Added**:
  - Driver `TelkomselDriver`: Integrasi pengisian pulsa & data Telkomsel, serial number generation, dan verifikasi HMAC-SHA256 signature.
  - Driver `IndosatDriver`: Integrasi pulsa & paket data Indosat Ooredoo dan verifikasi webhook.
  - Driver `XlDriver`: Integrasi pulsa & paket data XL Axiata / Axis dan verifikasi webhook.
  - Driver `PlnDriver`: Integrasi token prepaid & tagihan listrik postpaid lengkap dengan HMAC signature verification.
  - Driver `PdamDriver`: Integrasi tagihan air PDAM lengkap dengan HMAC signature verification.
  - Service `CircuitBreakerService` (ADR-003): Pemantauan status supplier (`CLOSED`, `OPEN`, `HALF-OPEN`), failure threshold counter, cooldown recovery, dan fail-fast protection.
  - Service `PpobService`: Dynamic supplier routing, integrasi CircuitBreaker (penolakan langsung HTTP 503 `PROVIDER_UNAVAILABLE` saat circuit OPEN), dan verifikasi webhook signature terpusat.
  - Controller `WebhookController`:
    - `POST /ppob/callback` & `/api/v1/ppob/callback` dengan validasi keamanan berlapis:
      1. HMAC-SHA256 signature check (`X-Signature`) -> Tolak 401 `WEBHOOK_SIGNATURE_INVALID`.
      2. Timestamp tolerance check (`X-Timestamp`, maks 300 detik) -> Tolak 422 `WEBHOOK_TIMESTAMP_INVALID`.
      3. Idempotency `event_id` check pada `processed_webhook_events` -> Tolak 409 `WEBHOOK_DUPLICATE`.
    - Auto-update status transaksi (`success` / `failed`) dan auto-refund saldo jika provider gagal.
  - Feature tests:
    - `WebhookValidationTest` (7 test): Validasi signature, timestamp stale, event_id required, duplikasi 409 conflict, status update success, dan failure auto-refund.
    - `ProviderCircuitBreakerTest` (4 test): Threshold tripping, fail-fast rejection 503 `PROVIDER_UNAVAILABLE`, dan recovery.
    - `ProviderDriverTest` (7 test): Eksekusi pay & inquiry rejection untuk prepaid pada semua driver, serta verifikasi signature constant-time.
    - Total 18 test baru — suite keseluruhan kini 104 tests (384 assertions) 100% lulus.

---

## [v0.6.0] - 2026-10-06
### Selesai: Phase 6 — Transaction Domain
- **Added**:
  - Model `Transaction`: Mendukung skema tabel terpartisi, relasi `user()`, `product()`, `inquiry()`, status helpers (`isPending()`, `isProcessing()`, `isSuccess()`, `isFailed()`, `isRefunded()`), dan atribut failover (ADR-005).
  - Model `IdempotencyKey`: Model penyimpanan key idempotency per-user dengan relasi `user()`.
  - Service `TransactionService`:
    - Validasi keaktifan produk (`PRODUCT_INACTIVE`).
    - Aturan prepaid: `amount` dari klien diabaikan, menggunakan harga server (`ProductPricingService`).
    - Aturan postpaid: Wajib `inquiry_id` yang valid & belum kedaluwarsa (`INQUIRY_REQUIRED`, `INQUIRY_EXPIRED`), serta validasi kecocokan nominal (`INQUIRY_AMOUNT_MISMATCH`).
    - Debit saldo atomik via `WalletService::debit()` dengan row locking `SELECT ... FOR UPDATE`.
    - Routing queue asynchronous per-supplier sesuai ADR-002.
    - Penanganan refund otomatis (`failAndRefund`) jika transaksi provider gagal.
  - Jobs:
    - `ProcessTransactionJob`: Eksekusi pembayaran ke provider via `PpobService::pay()`, penanganan kegagalan dengan auto-refund saldo, dan dispatch pengecekan status jika async.
    - `CheckTransactionStatusJob`: Polling status berkala untuk provider async dan auto-refund jika status akhir gagal.
  - Controller & Requests:
    - `TransactionController`: `POST /transactions` (201 Created), `GET /transactions` (history paginasi & filter status), `GET /transactions/{id}` (404 `TRANSACTION_NOT_FOUND`).
    - `CreateTransactionRequest`: Validasi input transaksi.
    - `TransactionResource`: Representasi JSON OpenAPI termasuk nested `ProductResource`.
    - Integrasi middleware berurutan: `IdempotencyMiddleware` kemudian `PinTokenMiddleware:transaction`.
  - Feature tests:
    - `TransactionIdempotencyTest` (3 test): Idempotency key required & 409 conflict replay data asli.
    - `TransactionAmountValidationTest` (5 test): Prepaid client amount diabaikan, postpaid inquiry required, expired rejection, amount mismatch.
    - `TransactionBalanceTest` (2 test): Insufficient balance 422 & valid balance debit dengan mutasi.
    - `TransactionPinTest` (5 test): Header required, invalid, expired, used, dan single-use verification.
    - `TransactionFlowTest` (5 test): Show detail, pagination history, filter status, product active check, cross-user isolation.
    - `TransactionJobTest` (5 test): Async provider processing, failure auto-refund, polling check status.
    - Total 25 test baru — suite keseluruhan kini 86 tests (329 assertions) 100% lulus.

---

## [v0.5.0] - 2026-10-06
### Selesai: Phase 5 — Inquiry Domain
- **Added**:
  - Model `Inquiry`: Penyimpanan data inquiry tagihan produk postpaid dengan relasi `user()`, `product()`, casts `MoneyCast`, serta helpers `isExpired()`, `isUsable()`, dan `totalAmount()`.
  - Kontrak `PpobProviderInterface`: Metode `inquiry()`, `pay()`, `checkStatus()`, dan `verifyWebhookSignature()`.
  - Service `InquiryService`: Proteksi keras penolakan produk prepaid (`PRODUCT_TYPE_MISMATCH`, HTTP 422) dan produk inaktif (`PRODUCT_INACTIVE`, HTTP 422), integrasi ke `PpobService`, dan otomatisasi masa berlaku 10 menit (`expires_at`).
  - Drivers: Implementasi `PlnDriver` (inquiry tagihan listrik) dan `PdamDriver` (inquiry tagihan air) dengan dukungan mock response.
  - FormRequest `InquiryRequest`: Validasi input `sku_code` dan `customer_number` (alphanumeric).
  - Controller `InquiryController`: Endpoint `POST /inquiry` mengembalikan `InquiryResource` dalam envelope `ApiResponse` (HTTP 200).
  - Feature tests: `InquiryValidationTest` (4 test), `InquiryFlowTest` (3 test), dan `InquiryExpiryTest` (2 test) — total 11 test lulus 100%.

---

## [v0.4.0] - 2026-10-06
### Selesai: Phase 4 — Product Domain
- **Added**:
  - Model `Product`, `ProductCategory`, `Provider`, dan `ProductTierPrice` lengkap dengan relasi, query scopes `active()`, dan casting `MoneyCast`.
  - Service `ProductPricingService`: Perhitungan harga dinamis berdasarkan tier user (`user_tier_id`), dengan fallback otomatis ke `base_price + admin_fee`.
  - Endpoint `GET /products/categories` (`CategoryResource`) terurut `sort_order`.
  - Endpoint `GET /products` (`ProductResource`) dengan filter `category`, `provider`, dan `product_type` (`prepaid` / `postpaid`).
  - Database seeders `ProductCategorySeeder` dan `ProviderSeeder` (termasuk konfigurasi worker Horizon).
  - Feature tests: `ProductCatalogTest` (5 test) & `TierPricingTest` (3 test).

---

## [v0.3.0] - 2026-10-06
### Selesai: Phase 3 — Wallet Domain
- **Added**:
  - Model `Wallet`, `WalletMutation` (ledger append-only), dan `TopupRequest`.
  - `WalletService`: Satu-satunya pengubah saldo menggunakan `SELECT ... FOR UPDATE` dan transaksi database.
  - Driver `PaymentGatewayInterface`: `MidtransGatewayDriver` (Snap + SHA-512 webhook) & `FakeGatewayDriver`.
  - Service `TopupService`: Pengelolaan request topup, approval transfer manual oleh admin, dan webhook gateway.
  - Middleware `IdempotencyMiddleware`: Validasi header `Idempotency-Key` (HTTP 409 `IDEMPOTENCY_CONFLICT` jika duplikat).
  - Controller `WalletController` (saldo & mutasi) dan `TopupController` (`POST /wallet/topup`, 201 Created).
  - Feature tests: `TopupIdempotencyTest`, `WalletRaceConditionTest`, `WalletFlowTest`, `TopupWebhookTest` (16 test).

---

## [v0.2.0] - 2026-10-05
### Selesai: Phase 2 — Security Domain (PIN)
- **Added**:
  - Model `PinVerificationToken` (token verifikasi transaksi sekali pakai, TTL 5 menit).
  - Service `PinService`: Alur `setPin`, `changePin`, `challenge` (UUID di Cache), dan `verify`.
  - Proteksi brute force PIN: Maksimal 5 kali percobaan salah dalam 15 menit mengunci akun (`PIN_LOCKED`, HTTP 429).
  - Middleware `PinTokenMiddleware`: Validasi header `X-Pin-Token` dan konsumsi sekali pakai.
  - Controller `PinController` & form requests.
  - Feature tests: `PinFlowTest` (7 test) & `PinBruteForceTest` (3 test).

---

## [v0.1.0] - 2026-10-05
### Selesai: Phase 0 & Phase 1 — Foundation & Auth Domain
- **Added**:
  - Value object `Money` & Eloquent cast `MoneyCast` (semua nominal uang dalam integer cents).
  - Standard response envelope `ApiResponse` & base controller `ApiController`.
  - Model `User`, `UserTier`, `OtpCode`.
  - Service `AuthService` & `OtpService` (rate limit OTP, driver `FonnteOtpDriver`).
  - Sanctum token authentication (`/auth/register`, `/auth/login`, `/auth/otp/verify`, `/auth/logout`, `/auth/me`).
  - Feature tests: `RegisterLoginTest` & `OtpRateLimitTest` (9 test).
