# Task Spec: Phase 10 — Step 3: Manajemen Transaksi & Intervensi Manual

> **Dokumen Acuan**:
> - `docs/admin-web-architecture.md` (Spesifikasi Transaksi & Manual Intervention)
> - `docs/task-specs/phase-6-transaction.md` (Lifecycle Transaksi)
> - `docs/task-specs/phase-3-wallet.md` (WalletService & Ledger Mutasi)
> - `docs/task-specs/phase-10-step1-auth.md` (Admin Guard & Audit Trail)
>
> **Selesai = Kode Controller + Service + Blade Views + Modal Alpine + Tests Pass**

---

## 1. Konteks & Tujuan

Modul Transaksi adalah inti operasional customer service, tim ops, dan finance untuk:
1. **Pencarian & Filtering Cepat**: Mencari transaksi berdasarkan Ref ID, Trx ID, No Tujuan (MSISDN), User, Mitra, atau Provider dengan filter status & rentang tanggal.
2. **Timeline Lifecycle Lengkap**: Menampilkan kronologi transaksi dari INQUIRY → PENDING → PROCESSING → SUCCESS/FAILED, termasuk log request/response ke provider.
3. **Re-Query Status Provider**: Mengecek ulang status transaksi yang pending/gantung langsung ke provider gateway.
4. **Manual Refund Aman**: Melakukan refund saldo pengguna jika transaksi terbukti gagal di provider. **WAJIB** melalui `WalletService::credit()` dengan tipe `REFUND`.
5. **Manual Status Override**: Mengubah status transaksi jika provider membatalkan atau terjadi timeout permanen.
6. **Audit & Safety**: Seluruh role admin (`ops`, `finance`, `super_admin`) diizinkan melakukan re-query dan refund **asalkan wajib mengisi catatan alasan (reason)** dan **dicatat lengkap ke `admin_audit_logs`**, serta dilindungi **konfirmasi password** (`password.confirm`).

---

## 2. Struktur File Yang Dibuat

```
app/
└── Domain/
    └── Admin/
        ├── Http/
        │   ├── Controllers/
        │   │   └── Transaction/
        │   │       ├── AdminTransactionController.php
        │   │       ├── AdminTransactionRequeryController.php
        │   │       └── AdminTransactionRefundController.php
        │   └── Requests/
        │       ├── TransactionFilterRequest.php
        │       ├── ManualRefundRequest.php
        │       └── ManualStatusOverrideRequest.php
        └── Services/
            └── AdminTransactionManagementService.php
resources/
└── views/
    └── admin/
        └── transactions/
            ├── index.blade.php
            ├── show.blade.php
            ├── components/
            │   ├── _filter_bar.blade.php
            │   ├── _transaction_table.blade.php
            │   ├── _lifecycle_timeline.blade.php
            │   ├── _provider_payload_viewer.blade.php
            │   └── _related_wallet_mutations.blade.php
            └── modals/
                ├── _requery_confirm_modal.blade.php
                ├── _refund_modal.blade.php
                └── _override_modal.blade.php
routes/
└── admin.php (Group transactions)
tests/Feature/Admin/
└── Transactions/
    ├── AdminTransactionListTest.php
    ├── AdminTransactionRequeryTest.php
    ├── AdminManualRefundTest.php
    └── AdminStatusOverrideTest.php
```

---

## 3. Aturan Integritas Finansial (Hard Rules)

1. **JANGAN PERNAH** memodifikasi kolom `wallets.balance` secara langsung di controller admin!
2. **Refund WAJIB memanggil `WalletService::credit()`**:
   ```php
   $walletService->credit(
       wallet: $user->wallet,
       amount: $transaction->amount,
       type: MutationType::REFUND,
       referenceId: 'REFUND-' . $transaction->id,
       description: "Manual refund transaksi {$transaction->reference_id}. Alasan: {$reason}"
   );
   ```
3. **Cegah Double Refund**: Sebelum memproses refund, periksa apakah transaksi sudah berstatus `REFUNDED` atau sudah memiliki mutasi dengan referensi `REFUND-{$transaction->id}`. Gunakan database pessimistic lock (`lockForUpdate`).
4. **Masking Kredensial**: Payload request/response provider yang ditampilkan di UI wajib melakukan masking terhadap field sensitif seperti `api_key`, `secret`, `pin`, atau `password`.

---

## 4. Service: `AdminTransactionManagementService`

Class `App\Domain\Admin\Services\AdminTransactionManagementService`:

### 4.1 Method: `filterTransactions(TransactionFilterRequest $filters): LengthAwarePaginator`
- Query dengan eager loading: `['user', 'provider', 'product']`.
- Mendukung filter:
  - `q`: Search keyword (reference_id, provider_reference, target_account/msisdn).
  - `status`: PENDING, PROCESSING, SUCCESS, FAILED, REFUNDED.
  - `provider_id`: Filter provider tertentu.
  - `date_start` & `date_end`: Rentang tanggal `created_at`.
- Pagination: 25 baris per halaman.

### 4.2 Method: `requeryProvider(Transaction $transaction, Admin $admin): array`
- Mengambil driver gateway provider via `ProviderFactory` atau `ProviderGatewayInterface`.
- Memanggil `checkStatus($transaction->provider_reference ?? $transaction->reference_id)`.
- Jika status di provider berubah (misal dari Pending jadi Success/Failed):
  - Update status transaksi.
  - Catat log perubahan ke `admin_audit_logs`.
- Mengembalikan response hasil re-query untuk ditampilkan ke UI.

### 4.3 Method: `processManualRefund(Transaction $transaction, Admin $admin, string $reason): void`
Dalam satu DB transaction (`DB::transaction`):
1. Lock baris transaksi: `Transaction::where('id', $transaction->id)->lockForUpdate()->first()`.
2. Validasi: Status transaksi harus `FAILED` atau `PROCESSING` yang terkonfirmasi gagal di provider, dan belum pernah di-refund.
3. Eksekusi `WalletService::credit()`.
4. Update status transaksi menjadi `REFUNDED`.
5. Tulis audit trail via `AdminAuditLogService`:
   - `action`: `transaction.manual_refund`
   - `target`: `$transaction`
   - `reason`: `$reason`
   - `changes`: `['status_before' => $oldStatus, 'status_after' => 'REFUNDED', 'amount_refunded' => $transaction->amount]`

### 4.4 Method: `overrideStatus(Transaction $transaction, Admin $admin, TransactionStatus $newStatus, string $reason): void`
Dalam DB transaction:
1. Validasi transisi status yang valid.
2. Update `$transaction->status = $newStatus`.
3. Tulis audit trail via `AdminAuditLogService`.

---

## 5. Controllers & Endpoints

### 5.1 `AdminTransactionController`
- `index(TransactionFilterRequest $request): View`: Tampilkan list transaksi + filter bar.
- `show(Transaction $transaction): View`: Detail transaksi, lifecycle timeline, mutasi wallet terkait, payload provider.

### 5.2 `AdminTransactionRequeryController`
- `requery(Transaction $transaction, Request $request): RedirectResponse`:
  - Route: `POST /admin/transactions/{transaction}/requery`
  - Memanggil `AdminTransactionManagementService::requeryProvider()`.
  - Flash notification success/info dengan hasil status terbaru dari provider.

### 5.3 `AdminTransactionRefundController`
- `refund(Transaction $transaction, ManualRefundRequest $request): RedirectResponse`:
  - Route: `POST /admin/transactions/{transaction}/refund`
  - Middleware: `password.confirm:admin.password.confirm`
  - Validasi field `reason` (min 10 karakter, wajib menjelaskan nomor tiket atau alasan refund).
  - Eksekusi `processManualRefund()`.
  - Flash notification success "Refund sebesar Rp {amount} berhasil dikembalikan ke saldo user".

---

## 6. Routing (`routes/admin.php`)

```php
Route::middleware(['auth:admin', 'admin.csp'])->group(function () {
    // List & Detail Transaksi
    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');

    // Aksi Operasional
    Route::post('/transactions/{transaction}/requery', [AdminTransactionRequeryController::class, 'requery'])
        ->name('transactions.requery');

    // Aksi Finansial Kritis (Wajib Konfirmasi Password)
    Route::middleware(['password.confirm:admin.password.confirm'])->group(function () {
        Route::post('/transactions/{transaction}/refund', [AdminTransactionRefundController::class, 'refund'])
            ->name('transactions.refund');
        Route::post('/transactions/{transaction}/override-status', [AdminTransactionController::class, 'overrideStatus'])
            ->name('transactions.override');
    });
});
```

---

## 7. Feature Tests (Coverage Wajib)

File di `tests/Feature/Admin/Transactions/`:
1. `AdminTransactionListTest.php`:
   - `test_admin_can_view_transactions_table()`
   - `test_filtering_by_status_returns_only_matching_transactions()`
   - `test_searching_by_msisdn_or_reference_id()`
   - `test_date_range_filter_bounds()`

2. `AdminTransactionRequeryTest.php`:
   - `test_requery_calls_provider_gateway_and_updates_transaction_status()`
   - `test_requery_logs_activity_in_audit_table()`

3. `AdminManualRefundTest.php`:
   - `test_refund_requires_password_confirmation()`
   - `test_refund_credits_user_wallet_via_wallet_service()`
   - `test_refund_cannot_be_processed_twice_on_same_transaction()`
   - `test_refund_creates_audit_log_with_reason_and_changes()`
   - `test_refund_fails_if_reason_is_empty()`

4. `AdminStatusOverrideTest.php`:
   - `test_admin_can_override_status_with_valid_reason()`
   - `test_status_override_recorded_in_audit_logs()`
