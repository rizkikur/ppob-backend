# Backend Skeleton Structure — PPOB Backend

Dokumen ini mendeskripsikan struktur folder lengkap aplikasi.
Setiap file baru **harus ditempatkan sesuai struktur ini** — jangan taruh file di `app/Models` atau `app/Http/Controllers` default Laravel.

---

## Prinsip Arsitektur

- **Modular Monolith**: Kode diorganisir berdasarkan domain bisnis, bukan layer teknis
- **Domain-Oriented**: Setiap domain berdiri sendiri di `app/Domain/{DomainName}/`
- **Shared Kernel**: Kode lintas-domain di `app/Domain/Shared/`
- **No Coupling**: Domain tidak boleh import class dari domain lain secara langsung — gunakan interface atau event

---

## Struktur Lengkap

```
laravel-project/
├── app/
│   ├── Domain/
│   │   ├── Shared/                          # Shared kernel — lintas domain
│   │   │   ├── Http/
│   │   │   │   ├── ApiController.php        # Base controller dengan helper methods
│   │   │   │   └── ApiResponse.php          # Response envelope standar
│   │   │   ├── ValueObjects/
│   │   │   │   └── Money.php                # Money value object (WAJIB untuk semua uang)
│   │   │   ├── Casts/
│   │   │   │   └── MoneyCast.php            # Eloquent cast untuk kolom bigint → Money
│   │   │   ├── Exceptions/
│   │   │   │   ├── AppException.php         # Base exception dengan error_code
│   │   │   │   ├── ValidationException.php
│   │   │   │   └── BusinessException.php
│   │   │   ├── Middleware/
│   │   │   │   ├── IdempotencyMiddleware.php  # Cek & simpan idempotency key
│   │   │   │   └── PinTokenMiddleware.php     # Validasi X-Pin-Token header
│   │   │   └── Traits/
│   │   │       └── HasMoney.php             # Helper trait untuk model dengan money
│   │   │
│   │   ├── Auth/                            # Domain autentikasi & user
│   │   │   ├── Contracts/
│   │   │   │   └── OtpSenderInterface.php   # Interface untuk driver OTP
│   │   │   ├── Drivers/
│   │   │   │   ├── FonnteOtpDriver.php      # Implementasi via Fonnte WA
│   │   │   │   └── WaBizOtpDriver.php       # Implementasi via WA Business API
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── AuthController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   ├── RegisterRequest.php
│   │   │   │   │   ├── OtpRequestRequest.php
│   │   │   │   │   ├── OtpVerifyRequest.php
│   │   │   │   │   └── LoginRequest.php
│   │   │   │   └── Resources/
│   │   │   │       ├── UserResource.php
│   │   │   │       └── TokenResource.php
│   │   │   ├── Models/
│   │   │   │   ├── User.php
│   │   │   │   ├── UserTier.php
│   │   │   │   └── OtpCode.php
│   │   │   └── Services/
│   │   │       ├── AuthService.php
│   │   │       └── OtpService.php
│   │   │
│   │   ├── Security/                        # Domain PIN & token keamanan
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── PinController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   ├── SetPinRequest.php
│   │   │   │   │   ├── ChangePinRequest.php
│   │   │   │   │   ├── PinChallengeRequest.php
│   │   │   │   │   └── PinVerifyRequest.php
│   │   │   │   └── Resources/
│   │   │   │       └── PinTokenResource.php
│   │   │   ├── Models/
│   │   │   │   └── PinVerificationToken.php
│   │   │   └── Services/
│   │   │       └── PinService.php
│   │   │
│   │   ├── Wallet/                          # Domain dompet digital
│   │   │   ├── Contracts/
│   │   │   │   └── PaymentGatewayInterface.php  # Interface payment gateway
│   │   │   ├── Drivers/
│   │   │   │   ├── MidtransGatewayDriver.php
│   │   │   │   └── XenditGatewayDriver.php
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── WalletController.php
│   │   │   │   │   └── TopupController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   ├── TopupRequest.php
│   │   │   │   │   └── ManualConfirmRequest.php
│   │   │   │   └── Resources/
│   │   │   │       ├── WalletResource.php
│   │   │   │       ├── MutationResource.php
│   │   │   │       └── TopupResource.php
│   │   │   ├── Models/
│   │   │   │   ├── Wallet.php
│   │   │   │   ├── WalletMutation.php
│   │   │   │   └── TopupRequest.php
│   │   │   └── Services/
│   │   │       ├── WalletService.php        # SATU-SATUNYA yang boleh ubah saldo
│   │   │       └── TopupService.php
│   │   │
│   │   ├── Product/                         # Domain katalog produk
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── ProductController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   └── ProductListRequest.php
│   │   │   │   └── Resources/
│   │   │   │       ├── ProductResource.php
│   │   │   │       └── CategoryResource.php
│   │   │   ├── Models/
│   │   │   │   ├── Product.php
│   │   │   │   ├── ProductCategory.php
│   │   │   │   ├── Provider.php
│   │   │   │   └── ProductTierPrice.php
│   │   │   └── Services/
│   │   │       └── ProductPricingService.php  # Hitung harga berdasarkan tier user
│   │   │
│   │   ├── Inquiry/                         # Domain inquiry postpaid
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── InquiryController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   └── InquiryRequest.php
│   │   │   │   └── Resources/
│   │   │   │       └── InquiryResource.php
│   │   │   ├── Models/
│   │   │   │   └── Inquiry.php
│   │   │   └── Services/
│   │   │       └── InquiryService.php
│   │   │
│   │   ├── Transaction/                     # Domain transaksi PPOB
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── TransactionController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   └── CreateTransactionRequest.php
│   │   │   │   └── Resources/
│   │   │   │       └── TransactionResource.php
│   │   │   ├── Jobs/
│   │   │   │   ├── ProcessTransactionJob.php  # Async: kirim ke provider PPOB
│   │   │   │   └── CheckTransactionStatusJob.php  # Async: polling status
│   │   │   ├── Models/
│   │   │   │   ├── Transaction.php
│   │   │   │   └── IdempotencyKey.php
│   │   │   └── Services/
│   │   │       └── TransactionService.php
│   │   │
│   │   ├── Ppob/                            # Domain integrasi provider PPOB
│   │   │   ├── Contracts/
│   │   │   │   └── PpobProviderInterface.php  # Interface wajib untuk semua driver
│   │   │   ├── Drivers/
│   │   │   │   ├── TelkomselDriver.php      # Pulsa & Paket Data Telkomsel
│   │   │   │   ├── IndosatDriver.php        # Pulsa & Paket Data Indosat
│   │   │   │   ├── XlDriver.php             # Pulsa & Paket Data XL/Axis
│   │   │   │   ├── PlnDriver.php            # Token & Tagihan PLN
│   │   │   │   └── PdamDriver.php           # Tagihan PDAM
│   │   │   ├── DTOs/
│   │   │   │   └── ResolvedRoute.php        # DTO hasil resolusi rute supplier (ADR-004)
│   │   │   ├── Http/
│   │   │   │   └── Controllers/
│   │   │   │       └── WebhookController.php  # Menerima callback dari provider
│   │   │   ├── Models/
│   │   │   │   └── ProductSupplierRoute.php # Pemetaan multi-supplier per produk (ADR-004)
│   │   │   └── Services/
│   │   │       ├── CircuitBreakerService.php # Circuit Breaker per-supplier (ADR-003)
│   │   │       ├── PpobService.php          # Routing ke driver yang tepat
│   │   │       └── SupplierRoutingService.php # Failover routing multi-supplier (ADR-004/005)
│   │   │
│   │   └── Partner/                         # Domain Open API partner
│   │       ├── Commands/
│   │       │   └── RetryFailedWebhooksCommand.php # Scheduler re-dispatch callback (ADR-008)
│   │       ├── Http/
│   │       │   ├── Controllers/
│   │       │   │   └── PartnerController.php
│   │       │   ├── Middleware/
│   │       │   │   └── PartnerAuthMiddleware.php  # Validasi API Key + HMAC
│   │       │   ├── Requests/
│   │       │   │   └── CreatePartnerTransactionRequest.php
│   │       │   └── Resources/
│   │       │       ├── PartnerProductResource.php
│   │       │       └── PartnerTransactionResource.php
│   │       ├── Jobs/
│   │       │   └── DeliverWebhookJob.php    # Async callback retry exponential backoff (ADR-008)
│   │       ├── Models/
│   │       │   ├── Partner.php
│   │       │   ├── PartnerLog.php
│   │       │   ├── PartnerProductPrice.php  # Harga flat khusus partner (ADR-006)
│   │       │   ├── PartnerRoutingRule.php   # Aturan failover per partner (ADR-004)
│   │       │   └── WebhookDelivery.php      # Tracking retry webhook partner (ADR-008)
│   │       └── Services/
│   │           └── PartnerAuthService.php
│   │
│   ├── Http/
│   │   └── Middleware/
│   │       └── (default Laravel middleware)
│   ├── Models/                              # KOSONG — semua model ada di domain
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── DomainServiceProvider.php       # Register semua binding domain
│
├── database/
│   ├── migrations/
│   │   ├── 2026_10_01_000001_create_user_tiers_table.php
│   │   ├── 2026_10_01_000002_create_users_table.php
│   │   ├── 2026_10_01_000003_create_otp_codes_table.php
│   │   ├── 2026_10_01_000004_create_pin_verification_tokens_table.php
│   │   ├── 2026_10_01_000005_create_wallets_table.php
│   │   ├── 2026_10_01_000006_create_wallet_mutations_table.php
│   │   ├── 2026_10_01_000007_create_topup_requests_table.php
│   │   ├── 2026_10_01_000008_create_product_categories_table.php
│   │   ├── 2026_10_01_000009_create_providers_table.php
│   │   ├── 2026_10_01_000010_create_products_table.php
│   │   ├── 2026_10_01_000011_create_product_tier_prices_table.php
│   │   ├── 2026_10_01_000012_create_inquiries_table.php
│   │   ├── 2026_10_01_000013_create_transactions_table.php   ← raw SQL partisi
│   │   ├── 2026_10_01_000014_create_idempotency_keys_table.php
│   │   ├── 2026_10_01_000015_create_processed_webhook_events_table.php
│   │   ├── 2026_10_01_000016_create_partners_table.php
│   │   ├── 2026_10_01_000017_create_partner_logs_table.php
│   │   ├── 2026_10_05_000001_add_adr_columns_to_existing_tables.php
│   │   ├── 2026_10_05_000002_create_routing_tables.php
│   │   ├── 2026_10_05_000003_create_partner_product_prices_table.php
│   │   ├── 2026_10_05_000004_create_webhook_deliveries_table.php
│   │   ├── 2026_10_05_000005_create_monitoring_tables.php
│   │   └── 2026_10_06_000001_add_user_id_to_partners_table.php

│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── UserTierSeeder.php
│       ├── ProductCategorySeeder.php
│       └── ProviderSeeder.php
│
├── routes/
│   ├── api.php                              # Import sub-route files
│   ├── api/
│   │   ├── auth.php                        # /auth/*
│   │   ├── security.php                    # /security/*
│   │   ├── wallet.php                      # /wallet/*
│   │   ├── products.php                    # /products/*
│   │   ├── inquiry.php                     # /inquiry/*
│   │   ├── transactions.php                # /transactions/*
│   │   └── webhooks.php                    # /ppob/callback, /wallet/callback
│   └── partner.php                         # /partner/* (Open API)
│
├── config/
│   └── ppob.php                            # Konfigurasi khusus PPOB
│
├── docs/
│   ├── ERD.md
│   ├── security-design.md
│   ├── backend-skeleton-structure.md       ← file ini
│   ├── dev-roadmap.md
│   └── openapi.yaml
│
└── tests/
    ├── Feature/
    │   ├── Auth/
    │   ├── Security/
    │   ├── Wallet/
    │   │   ├── WalletBalanceTest.php
    │   │   ├── WalletIdempotencyTest.php   ← wajib ada
    │   │   └── WalletRaceConditionTest.php ← wajib ada
    │   ├── Product/
    │   ├── Inquiry/
    │   ├── Transaction/
    │   │   ├── TransactionCreateTest.php
    │   │   ├── TransactionIdempotencyTest.php ← wajib ada
    │   │   └── PrepaidPostpaidValidationTest.php ← wajib ada
    │   ├── Ppob/
    │   └── Partner/
    └── Unit/
        ├── MoneyTest.php
        └── PinServiceTest.php
```

---

## Konvensi Penamaan

| Jenis File         | Konvensi                          | Contoh                          |
|--------------------|-----------------------------------|---------------------------------|
| Controller         | `{Resource}Controller`            | `TransactionController`         |
| Service            | `{Domain}Service`                 | `WalletService`                 |
| Model              | Singular, PascalCase              | `WalletMutation`                |
| Request            | `{Action}{Resource}Request`       | `CreateTransactionRequest`      |
| Resource           | `{Resource}Resource`              | `TransactionResource`           |
| Job                | `{Action}Job`                     | `ProcessTransactionJob`         |
| Interface          | `{Capability}Interface`           | `PpobProviderInterface`         |
| Driver             | `{ProviderName}Driver`            | `TelkomselDriver`               |
| Migration          | `{timestamp}_create_{table}_table`| `2026_10_01_000013_create_...`  |

---

## Aturan Import Antar Domain

```
✅ Domain boleh import dari:
   - app/Domain/Shared/*
   - Sendiri (sesama domain)
   - Laravel framework classes
   - Vendor packages

❌ Domain TIDAK BOLEH import langsung dari domain lain:
   - Salah: use App\Domain\Wallet\Services\WalletService; (di dalam TransactionService)
   - Benar: Inject interface, atau gunakan event/listener
```

---

## Response Envelope Standar

Semua response API menggunakan format ini (via `ApiResponse`):

```json
// Sukses
{
    "success": true,
    "data": { ... },
    "meta": { "page": 1, "per_page": 15, "total": 100 }
}

// Error
{
    "success": false,
    "error_code": "INSUFFICIENT_BALANCE",
    "message": "Saldo tidak mencukupi untuk transaksi ini",
    "errors": {}
}
```
