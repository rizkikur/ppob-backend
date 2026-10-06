# Task Spec: Phase 3 — Wallet Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (domain: Wallet), `docs/security-design.md` (section 9, 11), `docs/openapi.yaml` (endpoint /wallet, /wallet/mutations, /wallet/topup), `docs/dev-roadmap.md` (items 3.1–3.10).
>
> **Selesai = kode + test lulus + update dev-roadmap.md**

---

## Konteks

Phase 3 mengimplementasikan pengelolaan saldo dompet digital pengguna:
1. Uang selalu menggunakan `Money` value object (`balance_cents` bigint di database, di-cast ke `Money`).
2. Semua mutasi saldo wajib melewati `WalletService` menggunakan `SELECT ... FOR UPDATE` dan insert ke `wallet_mutations` dalam satu transaksi database.
3. Tabel `wallet_mutations` bersifat **append-only** (tidak boleh di-UPDATE atau di-DELETE).
4. Endpoint yang mengubah saldo/melakukan request transaksi (`/wallet/topup`) wajib memvalidasi `Idempotency-Key` (header `Idempotency-Key`).
5. Dukungan metode top-up:
   - `payment_gateway` (`midtrans`, `xendit`): berinteraksi dengan gateway driver, menghasilkan URL pembayaran, dan status diupdate saat menerima webhook/callback.
   - `manual_transfer`: dibuat dengan status `pending`, dikonfirmasi oleh admin.

---

## File yang Dibuat & Dikelola

```
app/Domain/Wallet/
├── Models/
│   ├── Wallet.php
│   ├── WalletMutation.php
│   └── TopupRequest.php
├── Contracts/
│   └── PaymentGatewayInterface.php
├── Drivers/
│   ├── MidtransGatewayDriver.php
│   └── FakeGatewayDriver.php
├── Services/
│   ├── WalletService.php
│   └── TopupService.php
├── Exceptions/
│   ├── InsufficientBalanceException.php
│   ├── TopupAlreadyProcessedException.php
│   └── InvalidPaymentGatewayException.php
├── Http/
│   ├── Controllers/
│   │   ├── WalletController.php
│   │   └── TopupController.php
│   ├── Requests/
│   │   └── TopupRequestForm.php
│   └── Resources/
│       ├── WalletResource.php
│       ├── MutationResource.php
│       └── TopupResource.php
app/Domain/Shared/Middleware/
└── IdempotencyMiddleware.php
tests/Feature/Wallet/
├── WalletFlowTest.php
├── WalletRaceConditionTest.php
├── TopupIdempotencyTest.php
└── TopupWebhookTest.php
```

---

## Rincian Item Pekerjaan

### Item 3.1 — Models: `Wallet`, `WalletMutation`, `TopupRequest`
- **Wallet**:
  - Kolom: `id`, `user_id`, `balance_cents`, `created_at`, `updated_at`.
  - Casts: `balance_cents` => `MoneyCast::class`.
  - Relasi: `user()`, `mutations()`.
  - Helper: `balance(): Money`, `hasSufficientBalance(Money $amount): bool`.
- **WalletMutation**:
  - Kolom: `id`, `wallet_id`, `user_id`, `type`, `amount_cents`, `balance_after_cents`, `reference_type`, `reference_id`, `note`, `created_at`.
  - Append-only (`UPDATED_AT = null`).
  - Casts: `amount_cents` => `MoneyCast::class`, `balance_after_cents` => `MoneyCast::class`.
  - Relasi: `wallet()`, `user()`.
- **TopupRequest**:
  - Kolom: `id`, `user_id`, `amount_cents`, `method`, `payment_gateway`, `gateway_ref`, `gateway_payload`, `status`, `idempotency_key`, `paid_at`, `confirmed_by`, `confirmed_at`, `created_at`, `updated_at`.
  - Casts: `amount_cents` => `MoneyCast::class`, `gateway_payload` => `'array'`, timestamps.
  - Relasi: `user()`, `confirmedBy()`.
  - Helper: `isPaid()`, `isPending()`.

### Item 3.2 — Trigger DB Append-Only
- Trigger PostgreSQL sudah didefinisikan pada migration `2026_10_01_000006_create_wallet_mutations_table.php`.
- Memastikan tidak ada logika aplikasi yang memanggil update/delete pada `WalletMutation`.

### Item 3.3 — WalletService
- Lokasi: `App\Domain\Wallet\Services\WalletService`.
- Method:
  - `getOrCreateWallet(User $user): Wallet`
  - `getBalance(User $user): Money`
  - `credit(User $user, Money $amount, string $referenceType, ?int $referenceId = null, ?string $note = null): WalletMutation`
  - `debit(User $user, Money $amount, string $referenceType, ?int $referenceId = null, ?string $note = null): WalletMutation`
- Enforce `lockForUpdate()` dalam `DB::transaction()`.
- Validasi kecukupan saldo: jika kurang, throw exception dengan error code `INSUFFICIENT_BALANCE` (HTTP 422).

### Item 3.4 & 3.5 — TopupService & Payment Gateway Driver
- **PaymentGatewayInterface**:
  - `createPayment(TopupRequest $topup, Money $amount): array`
  - `verifyWebhook(array $payload, string $signature): bool`
  - `parseWebhookStatus(array $payload): string`
  - `parseWebhookRef(array $payload): string`
- **MidtransGatewayDriver** & **FakeGatewayDriver**:
  - Implementasi driver untuk pembuatan invoice/snap URL dan validasi signature SHA512.
- **TopupService**:
  - `createTopup(User $user, Money $amount, string $method, ?string $gateway = null, ?string $idempotencyKey = null): TopupRequest`
  - `markAsPaid(TopupRequest $topup): TopupRequest`: memanggil `WalletService::credit()`, update status ke `paid`, `paid_at = now()`. Idempotent (jika sudah paid, tidak double-credit).
  - `confirmManual(TopupRequest $topup, User $admin): TopupRequest`
  - `handleWebhook(string $gateway, array $payload, string $signature): TopupRequest`

### Item 3.6 — Controllers, Requests & Resources
- **WalletController**:
  - `GET /wallet` → `balance(Request $request)`: return `WalletResource`.
  - `GET /wallet/mutations` → `mutations(Request $request)`: return paginated `MutationResource`. Filter `type=credit|debit`, `per_page`.
- **TopupController**:
  - `POST /wallet/topup` → `topup(TopupRequestForm $request)`: validasi `amount`, `method`, `payment_gateway`. Diproteksi `IdempotencyMiddleware`. Return `TopupResource` (status 201).
- **TopupResource**:
  - Sertakan `payment_url` dari `gateway_payload['payment_url']` jika ada.

### Item 3.7 — IdempotencyMiddleware
- Memeriksa header `Idempotency-Key`.
- Jika hilang: return HTTP 422 `IDEMPOTENCY_KEY_REQUIRED`.
- Jika panjang > 100: return HTTP 422 `VALIDATION_ERROR`.
- Cek tabel `idempotency_keys` untuk `(user_id, key_value)`.
- Jika sudah ada: return HTTP 409 `IDEMPOTENCY_CONFLICT` dengan response body asli.
- Jika belum: lanjutkan request, jika response code 2xx simpan ke `idempotency_keys`.

### Item 3.8 – 3.10 — Feature Tests
1. **TopupIdempotencyTest**:
   - Request pertama sukses HTTP 201.
   - Request kedua dengan key sama mengembalikan HTTP 409 `IDEMPOTENCY_CONFLICT` dan data yang identik.
   - Request tanpa `Idempotency-Key` ditolak dengan HTTP 422 `IDEMPOTENCY_KEY_REQUIRED`.
2. **WalletRaceConditionTest**:
   - Simulasi debit ketika saldo tidak mencukupi langsung ditolak dengan `INSUFFICIENT_BALANCE`.
   - Saldo dompet tidak pernah negatif.
3. **WalletFlowTest**:
   - Inisialisasi wallet otomatis jika belum ada saat cek saldo.
   - Balance inquiry menghasilkan data saldo yang tepat.
   - Riwayat mutasi tercatat rapi dan mendukung filter per tipe.
   - Flow request topup gateway & manual.
4. **TopupWebhookTest**:
   - Webhook valid mengkredit saldo dan mencatat mutasi `topup`.
   - Webhook duplikat tidak menyebabkan penambahan saldo ganda.

---

## Definition of Done Phase 3
- [x] Model `Wallet`, `WalletMutation`, `TopupRequest` lengkap dengan relasi dan casting
- [x] `WalletService` menggunakan `SELECT ... FOR UPDATE` dan DB transaction
- [x] `TopupService` mengelola pembuatan top-up dan konfirmasi kredit
- [x] `MidtransGatewayDriver` & `FakeGatewayDriver` terintegrasi via `PaymentGatewayInterface`
- [x] `WalletController` dan `TopupController` menggunakan `ApiResponse`/`ApiController`
- [x] `IdempotencyMiddleware` memverifikasi header dan mencegah duplikasi
- [x] Semua test (WalletFlow, WalletRaceCondition, TopupIdempotency, TopupWebhook) lulus 100%
- [x] Seluruh kode UTF-8 tanpa BOM dan diformat dengan Laravel Pint
- [x] `docs/dev-roadmap.md` diupdate ke `✅ Selesai`
