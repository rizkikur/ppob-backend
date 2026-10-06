# Development Roadmap — PPOB Backend

Urutan pengerjaan domain berdasarkan ketergantungan (dependency-first).
Update bagian **Status** setiap item selesai.

---

## Aturan Pengerjaan

- Satu sesi fokus ke satu domain sesuai urutan di bawah
- Domain tidak boleh dikerjakan sebelum dependensinya selesai
- Setiap item selesai = kode + test lulus + dokumen diperbarui
- Update kolom **Status** langsung di file ini

---

## Status Progress

### Phase 0 — Foundation (Wajib selesai sebelum domain manapun)

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 0.1 | Buat dokumen desain (ERD, security, dll)      | ✅ Selesai     | `/docs/*.md` + `/docs/openapi.yaml`              |
| 0.2 | Buat skeleton folder + base classes           | ✅ Selesai     | `ApiController`, `ApiResponse`, `Money`, `MoneyCast` |
| 0.3 | Setup `DomainServiceProvider`                 | ✅ Selesai     | Register semua binding interface → driver        |
| 0.4 | Buat konfigurasi `config/ppob.php`            | ✅ Selesai     | OTP driver, payment gateway, provider creds      |
| 0.5 | Buat semua migration (struktur saja)          | ✅ Selesai     | 17 migration sesuai ERD.md                       |
| 0.6 | Setup route stubs untuk semua domain          | ✅ Selesai     | `routes/api/*.php` + `routes/partner.php`        |

---

### Phase 1 — Auth Domain

Dependensi: Phase 0

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 1.1 | Model: `User`, `UserTier`, `OtpCode`          | ✅ Selesai     | Model User, UserTier, OtpCode lengkap            |
| 1.2 | Service: `OtpService` (request & verify OTP)  | ✅ Selesai     | Rate limit Redis/Cache, maks 3 attempt, expire 5m|
| 1.3 | Driver: `FonnteOtpDriver`                     | ✅ Selesai     | HTTP call ke Fonnte API + FakeOtpDriver          |
| 1.4 | Service: `AuthService` (register, login, logout)| ✅ Selesai    | Issue & revoke Sanctum token                     |
| 1.5 | Controller + Request + Resource: Auth         | ✅ Selesai     | `AuthController` + envelope ApiResponse          |
| 1.6 | Feature test: register + OTP flow             | ✅ Selesai     | 7 test case di `RegisterLoginTest.php`           |
| 1.7 | Feature test: login + logout                  | ✅ Selesai     | 2 test case di `OtpRateLimitTest.php`            |

---

### Phase 2 — Security Domain (PIN)

Dependensi: Phase 1

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 2.1 | Model: `PinVerificationToken`                 | ✅ Selesai     | Model token + relasi user + expiry/used helpers  |
| 2.2 | Service: `PinService` (set, change, challenge, verify) | ✅ Selesai | Brute force protection 5 attempt / 15 menit    |
| 2.3 | Controller + Request: PIN                     | ✅ Selesai     | `PinController` + requests + ApiResponse envelope|
| 2.4 | Middleware: `PinTokenMiddleware`              | ✅ Selesai     | Validasi & konsumsi `X-Pin-Token` header         |
| 2.5 | Feature test: PIN flow end-to-end             | ✅ Selesai     | 7 test case di `PinFlowTest.php`                 |
| 2.6 | Feature test: brute force protection          | ✅ Selesai     | 3 test case di `PinBruteForceTest.php`           |

---

### Phase 3 — Wallet Domain

Dependensi: Phase 1, Phase 2

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 3.1 | Model: `Wallet`, `WalletMutation`, `TopupRequest` | ✅ Selesai     | Model wallet, mutation, topup lengkap relasi & casting |
| 3.2 | Trigger DB append-only di migration           | ✅ Selesai     | Trigger immutable di PostgreSQL & append-only Eloquent |
| 3.3 | Service: `WalletService` (credit, debit)      | ✅ Selesai     | SELECT FOR UPDATE + DB transaction + append mutation |
| 3.4 | Service: `TopupService` (gateway + manual)    | ✅ Selesai     | Gateway redirect, webhook handler, manual approval |
| 3.5 | Driver: `MidtransGatewayDriver`               | ✅ Selesai     | Driver Midtrans Snap + FakeGatewayDriver testing |
| 3.6 | Controller: `WalletController`, `TopupController` | ✅ Selesai | Cek saldo, list mutasi, topup via ApiResponse    |
| 3.7 | Middleware: `IdempotencyMiddleware`            | ✅ Selesai     | Validasi Idempotency-Key, return 409 jika duplikat |
| 3.8 | Feature test: idempotency top-up              | ✅ Selesai     | 3 test case di `TopupIdempotencyTest.php`         |
| 3.9 | Feature test: race condition wallet           | ✅ Selesai     | 3 test case di `WalletRaceConditionTest.php`      |
| 3.10| Feature test: balance inquiry & topup flow    | ✅ Selesai     | 6 test case di `WalletFlowTest.php` + 4 di Webhook|

---

### Phase 4 — Product Domain

Dependensi: Phase 0

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 4.1 | Model: `Product`, `ProductCategory`, `Provider`, `ProductTierPrice` | ✅ Selesai | Model katalog, relasi, scope active, Money casting |
| 4.2 | Service: `ProductPricingService`              | ✅ Selesai     | Hitung harga per user tier + fallback base+admin |
| 4.3 | Controller + Resource: Product list           | ✅ Selesai     | Filter by kategori, operator, product_type       |
| 4.4 | Seeder: kategori & provider awal              | ✅ Selesai     | Pulsa, Paket Data, Token Listrik, Tagihan, PDAM  |
| 4.5 | Feature test: tier pricing & katalog          | ✅ Selesai     | 8 test case di `ProductCatalogTest` & `TierPricingTest` |

---

### Phase 5 — Inquiry Domain

Dependensi: Phase 4, Phase 1

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 5.1 | Model: `Inquiry`                              | ✅ Selesai     | Model inquiry, relasi user & product, totalAmount |
| 5.2 | Interface: `PpobProviderInterface`            | ✅ Selesai     | Method: `inquiry()`, `pay()`, `checkStatus()`, dsb |
| 5.3 | Service: `InquiryService`                     | ✅ Selesai     | Tolak SKU prepaid, expire 10 menit               |
| 5.4 | Driver: `PlnDriver` (inquiry tagihan)         | ✅ Selesai     | Driver inquiry tagihan listrik PLN + mock support |
| 5.5 | Driver: `PdamDriver` (inquiry tagihan)        | ✅ Selesai     | Driver inquiry tagihan air PDAM + mock support   |
| 5.6 | Controller + Request: Inquiry                 | ✅ Selesai     | `POST /inquiry` + `InquiryResource` via ApiController |
| 5.7 | Feature test: validasi prepaid/postpaid       | ✅ Selesai     | 4 test case di `InquiryValidationTest.php`       |
| 5.8 | Feature test: inquiry expire & flow           | ✅ Selesai     | 3 di `InquiryFlowTest` + 2 di `InquiryExpiryTest`|

---

### Phase 6 — Transaction Domain

Dependensi: Phase 3, Phase 5, Phase 2

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 6.1 | Model: `Transaction`, `IdempotencyKey`        | ✅ Selesai     | Model transaksi terpartisi + relasi + status helper |
| 6.2 | Migration: partisi `transactions` per bulan   | ✅ Selesai     | Raw SQL `DB::statement` di PostgreSQL + sqlite support |
| 6.3 | Service: `TransactionService`                 | ✅ Selesai     | Idempotency, PIN, saldo via WalletService, queue per supplier |
| 6.4 | Job: `ProcessTransactionJob`                  | ✅ Selesai     | Eksekusi bayar ke PpobService, update status, auto-refund |
| 6.5 | Job: `CheckTransactionStatusJob`              | ✅ Selesai     | Polling status async provider & auto-refund jika gagal |
| 6.6 | Controller + Request: Transaction             | ✅ Selesai     | `POST /transactions`, `GET /transactions`, `GET /transactions/{id}` |
| 6.7 | Feature test: idempotency transaksi           | ✅ Selesai     | **WAJIB** — 3 test di `TransactionIdempotencyTest.php` (409 conflict)|
| 6.8 | Feature test: validasi prepaid amount         | ✅ Selesai     | **WAJIB** — nominal klien diabaikan untuk prepaid |
| 6.9 | Feature test: validasi postpaid amount match  | ✅ Selesai     | **WAJIB** — inquiry wajib & amount mismatch ditolak (422) |
| 6.10| Feature test: insufficient balance            | ✅ Selesai     | 2 test di `TransactionBalanceTest.php` (422 & mutasi debit) |
| 6.11| Feature test: PIN token required              | ✅ Selesai     | 5 test di `TransactionPinTest.php` (invalid, expired, used) |

---

### Phase 7 — PPOB Integration Domain

Dependensi: Phase 6

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 7.1 | Driver: `TelkomselDriver` (pulsa + paket data)| ✅ Selesai     | Driver pulsa & paket data + serial number + HMAC |
| 7.2 | Driver: `IndosatDriver`                       | ✅ Selesai     | Driver pulsa & paket data + serial number + HMAC |
| 7.3 | Driver: `XlDriver`                            | ✅ Selesai     | Driver pulsa & paket data + serial number + HMAC |
| 7.4 | Driver: `PlnDriver` (token + tagihan)         | ✅ Selesai     | Token listrik prepaid + tagihan postpaid + HMAC  |
| 7.5 | Driver: `PdamDriver`                          | ✅ Selesai     | Tagihan air PDAM + HMAC signature verification   |
| 7.6 | Service: `PpobService` (routing ke driver)    | ✅ Selesai     | Router driver + Circuit Breaker (ADR-003)        |
| 7.7 | Controller: `WebhookController`               | ✅ Selesai     | Validasi HMAC signature, timestamp, event_id     |
| 7.8 | Feature test: webhook validasi berlapis       | ✅ Selesai     | 7 test di `WebhookValidationTest.php`            |
| 7.9 | Feature test: provider unavailable handling   | ✅ Selesai     | 4 test di `ProviderCircuitBreakerTest.php` (503) |

---

### Phase 8 — Partner / Open API Domain

Dependensi: Phase 6

| No  | Item                                          | Status         | Catatan                                          |
|-----|-----------------------------------------------|----------------|--------------------------------------------------|
| 8.1 | Model: `Partner`, `PartnerLog`                | ⬜ Belum       |                                                  |
| 8.2 | Service: `PartnerAuthService` (validasi API Key + HMAC) | ⬜ Belum | constant-time compare                        |
| 8.3 | Middleware: `PartnerAuthMiddleware`            | ⬜ Belum       | IP whitelist, rate limit, signature               |
| 8.4 | Controller: `PartnerController`               | ⬜ Belum       | Expose subset endpoint untuk partner             |
| 8.5 | Feature test: partner auth flow               | ⬜ Belum       | API key salah → 401, HMAC salah → 401             |
| 8.6 | Feature test: partner rate limit              | ⬜ Belum       |                                                  |
| 8.7 | Feature test: IP whitelist                    | ⬜ Belum       |                                                  |

---

## Dependensi Antar Phase

```
Phase 0 (Foundation)
    └── Phase 1 (Auth)
            └── Phase 2 (Security/PIN)
            └── Phase 5 (Inquiry) ─── Phase 4 (Product)
                    └── Phase 6 (Transaction)
                            └── Phase 3 (Wallet) ─── Phase 2
                            └── Phase 7 (PPOB)
                            └── Phase 8 (Partner)
```

---

## Definition of Done per Item

Sebuah item dianggap **selesai** jika:
- [ ] Kode terimplementasi sesuai arsitektur di `backend-skeleton-structure.md`
- [ ] Tidak ada pelanggaran aturan keras di `CLAUDE.md`
- [ ] Semua test yang wajib ada (idempotency, race condition, validasi) sudah lulus
- [ ] Dokumen yang relevan (ERD, security-design) sudah diperbarui jika ada perubahan skema
- [ ] `./vendor/bin/phpstan analyse` tidak ada error di level konfigurasi saat ini
