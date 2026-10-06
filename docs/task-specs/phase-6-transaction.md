# Task Spec: Phase 6 — Transaction Domain

> **Baca dulu sebelum coding**: `CLAUDE.md`, `docs/ERD.md` (Domain: Transaction), `docs/openapi.yaml` (endpoint /transactions), `docs/security-design.md` (section 2, 11), `docs/architecture-decisions.md` (ADR-002, ADR-005), `docs/dev-roadmap.md` (Phase 6).
>
> **Selesai = kode + test lulus + update changelog & dev-roadmap.md + push branch**

---

## Konteks

Phase 6 mengimplementasikan domain transaksi PPOB (pembelian pulsa, paket data, token listrik, pembayaran tagihan):
1. **Aturan Idempotency**: Header `Idempotency-Key` wajib disertakan. Request identik kedua kali harus ditolak dengan HTTP 409 `IDEMPOTENCY_CONFLICT` dan data response pertama.
2. **Aturan PIN Token**: Header `X-Pin-Token` wajib disertakan dengan purpose `transaction`. Token sekali pakai dan kedaluwarsa setelah 5 menit.
3. **Aturan Prepaid vs Postpaid**:
   - Produk **prepaid**: parameter `amount` dari klien diabaikan. Harga diputuskan oleh server berdasarkan tier user saat itu (`ProductPricingService`).
   - Produk **postpaid**: wajib menyertakan `inquiry_id` yang masih berlaku (belum expired). Parameter `amount` dari klien harus cocok persis dengan `amount_cents` pada inquiry.
4. **Debit Saldo**: Pengurangan saldo pengguna **HANYA** boleh dilakukan melalui `WalletService::debit()` dengan row locking (`SELECT ... FOR UPDATE`) dalam database transaction.
5. **Asynchronous Processing**: Transaksi dibuat dalam status `pending`, lalu di-dispatch ke queue spesifik supplier (`ProcessTransactionJob`) sesuai ADR-002. Jika provider gagal, saldo di-refund otomatis via `WalletService::credit()`.

---

## File yang Dibuat & Dikelola

```
app/Domain/Transaction/
├── Models/
│   ├── Transaction.php
│   └── IdempotencyKey.php
├── Services/
│   └── TransactionService.php
├── Jobs/
│   ├── ProcessTransactionJob.php
│   └── CheckTransactionStatusJob.php
├── Http/
│   ├── Controllers/
│   │   └── TransactionController.php
│   ├── Requests/
│   │   └── CreateTransactionRequest.php
│   └── Resources/
│       └── TransactionResource.php
routes/api/
└── transactions.php
tests/Feature/Transaction/
├── TransactionIdempotencyTest.php
├── TransactionAmountValidationTest.php
├── TransactionBalanceTest.php
├── TransactionPinTest.php
├── TransactionFlowTest.php
└── TransactionJobTest.php
```

---

## Rincian Item Pekerjaan

### Item 6.1 — Model: `Transaction` & `IdempotencyKey`
- `Transaction`:
  - Relasi: `user()`, `product()`, `inquiry()`
  - Casting: `amount_cents` => `MoneyCast`, `sell_price_cents` => `MoneyCast`, `provider_response` => `'array'`
  - Helpers: `isPending()`, `isProcessing()`, `isSuccess()`, `isFailed()`, `isRefunded()`
- `IdempotencyKey`:
  - Relasi: `user()`
  - Field: `user_id`, `key_value`, `endpoint`, `response_code`, `response_body`

### Item 6.2 — Migration: Partisi Tabel `transactions`
- Tabel `transactions` dibuat dengan partisi range PostgreSQL per bulan (`DB::statement`), dengan fallback SQLite untuk test environment.
- Kolom pendukung ADR (ADR-002, ADR-005: `supplier_id`, `original_supplier_id`, `is_failover`, `partner_id`).

### Item 6.3 — Service: `TransactionService`
- Injeksi `WalletService`, `ProductPricingService`.
- Validasi status keaktifan produk (`PRODUCT_INACTIVE`).
- Validasi inquiry untuk produk postpaid (`INQUIRY_REQUIRED`, `INQUIRY_EXPIRED`, `INQUIRY_AMOUNT_MISMATCH`).
- Eksekusi debit saldo dan pencatatan transaksi dalam database transaction.
- Dispatch `ProcessTransactionJob` ke queue provider (ADR-002).

### Item 6.4 & 6.5 — Jobs: `ProcessTransactionJob` & `CheckTransactionStatusJob`
- `ProcessTransactionJob`: memanggil `PpobService::pay()`, update status transaksi, handle auto-refund saldo jika transaksi gagal.
- `CheckTransactionStatusJob`: polling provider async dan update status akhir.

### Item 6.6 — Controller + Request + Resource: Transaction
- `TransactionController`:
  - `POST /transactions` (store)
  - `GET /transactions` (index/history berpaginasi)
  - `GET /transactions/{id}` (show detail)
- `CreateTransactionRequest`: validasi format input `sku_code`, `customer_number`, `inquiry_id`, `amount`.
- `TransactionResource`: representasi JSON sesuai OpenAPI schema.

### Item 6.7 – 6.11 — Feature Tests
- 6.7: Idempotency (409 conflict replay)
- 6.8: Prepaid amount (client amount diabaikan)
- 6.9: Postpaid amount match (inquiry required, nominal mismatch 422)
- 6.10: Insufficient balance (422 INSUFFICIENT_BALANCE)
- 6.11: PIN token required (422 PIN_TOKEN_INVALID / EXPIRED / USED)
- Alur transaksi komprehensif & jobs execution.
