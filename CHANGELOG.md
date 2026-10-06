# Changelog

Semua perubahan dan catatan rilis pada proyek PPOB Backend didokumentasikan di sini.
Format mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/).

---

## [Unreleased]

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
