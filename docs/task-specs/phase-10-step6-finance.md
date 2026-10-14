# Task Spec: Phase 10 — Step 6: Keuangan, Rekonsiliasi & Ledger Saldo

> **Dokumen Acuan**:
> - `docs/admin-web-architecture.md` (Spesifikasi Keuangan & Rekonsiliasi)
> - `docs/task-specs/phase-3-wallet.md` (WalletService, Mutasi, dan Lock Concurrency)
> - `docs/task-specs/phase-10-step1-auth.md` (Audit Trail & Password Confirmation)
> - `docs/security-design.md` (Financial Safety & Double-Entry Ledger)
>
> **Selesai = Controller + Service + Blade Ledger + Export CSV Streaming + Tests Pass**

---

## 1. Konteks & Tujuan

Modul Keuangan adalah modul dengan risiko integritas data tertinggi pada sistem:
1. **Approval Top-Up Manual**: Memeriksa bukti transfer pengguna/mitra, menyetujui (`approve`) atau menolak (`reject`) request top-up.
2. **Penyesuaian Saldo Manual (Balance Adjustment)**: Mengkredit atau mendebit saldo pengguna untuk koreksi selisih, kompensasi, atau penyesuaian khusus.
3. **Pemeriksaan Ledger Mutasi (Double-Entry Ledger Viewer)**: Melihat seluruh mutasi saldo (`wallet_mutations`) dengan detail `balance_before`, `amount`, `balance_after`, `type`, dan referensi transaksi.
4. **Rekonsiliasi Saldo Provider**: Membandingkan saldo deposit yang tercatat di internal database dengan sisa saldo aktual di API masing-masing provider (Digiflazz, IAK, OTOMAX) untuk mendeteksi anomali/selisih.
5. **Ekspor Laporan (Streaming CSV)**: Ekspor mutasi dan rekap keuangan ke file CSV menggunakan metode streaming HTTP agar hemat memori PHP saat memproses ratusan ribu data.
6. **Kebijakan Otorisasi & Keamanan**:
   - Hanya role `finance_admin` dan `super_admin` yang dapat mengakses modul ini.
   - Sesuai kesepakatan, semua nominal penyesuaian saldo diizinkan diproses oleh admin berwenang **asalkan wajib mengisi catatan/alasan jelas**, dilindungi **konfirmasi password**, dan dicatat ke `admin_audit_logs`.

---

## 2. Struktur File Yang Dibuat

```
app/
└── Domain/
    └── Admin/
        ├── Http/
        │   ├── Controllers/
        │   │   └── Finance/
        │   │       ├── AdminTopupApprovalController.php
        │   │       ├── AdminWalletAdjustmentController.php
        │   │       ├── AdminLedgerMutationController.php
        │   │       └── AdminReconciliationController.php
        │   └── Requests/
        │       ├── ApproveTopupRequest.php
        │       ├── RejectTopupRequest.php
        │       ├── WalletAdjustmentRequest.php
        │       └── ExportLedgerCsvRequest.php
        └── Services/
            ├── AdminFinanceService.php
            └── AdminReconciliationService.php
resources/
└── views/
    └── admin/
        └── finance/
            ├── topups/
            │   ├── index.blade.php
            │   └── show.blade.php
            ├── adjustments/
            │   └── create.blade.php
            ├── ledger/
            │   └── index.blade.php
            └── reconciliation/
                ├── index.blade.php
                └── show.blade.php
routes/
└── admin.php (Group finance)
tests/Feature/Admin/
└── Finance/
    ├── AdminTopupApprovalTest.php
    ├── AdminWalletAdjustmentTest.php
    ├── AdminLedgerMutationViewTest.php
    └── AdminReconciliationTest.php
```

---

## 3. Aturan Keras Finansial (Financial Safety Hard Rules)

1. **MUTLAK**: Dilarang keras memanipulasi kolom `wallets.balance` secara manual menggunakan `->update(['balance' => ...])`!
2. **Approval Top-Up WAJIB lewat `TopupService::approve()` atau `WalletService::credit()`**:
   ```php
   $walletService->credit(
       wallet: $topupRequest->wallet,
       amount: $topupRequest->amount,
       type: MutationType::TOPUP,
       referenceId: 'TOPUP-' . $topupRequest->id,
       description: "Manual topup approval oleh {$admin->name}. Bukti: {$topupRequest->proof_image}"
   );
   ```
3. **Penyesuaian Saldo Manual WAJIB menggunakan tipe `ADJUSTMENT_CREDIT` atau `ADJUSTMENT_DEBIT`**:
   - Jika penyesuaian menambah saldo: panggil `WalletService::credit()`.
   - Jika penyesuaian memotong saldo: panggil `WalletService::debit()` dengan pemeriksaan saldo cukup (`InsufficientFundsException`).
4. **Idempotensi & Concurrency**:
   - Gunakan `lockForUpdate()` pada tabel `topup_requests` dan `wallets` dalam database transaction.
   - Cegah double-approval jika dua admin membuka halaman yang sama secara bersamaan.
5. **Streaming Export**: Dilarang memuat seluruh collection ke memori (`->get()`) saat ekspor CSV! Wajib menggunakan `cursor()` atau `chunk()` dengan `StreamedResponse`.

---

## 4. Services

### 4.1 `AdminFinanceService`
Class `App\Domain\Admin\Services\AdminFinanceService`:

- `approveTopup(TopupRequest $topup, Admin $admin, ?string $notes): void`:
  1. `DB::transaction()` dengan pessimistic lock `lockForUpdate()`.
  2. Cek status saat ini harus `PENDING`. Jika sudah diubah, lempar `InvalidStateException`.
  3. Panggil `WalletService::credit()` dengan tipe `TOPUP`.
  4. Update status `$topup->update(['status' => 'APPROVED', 'approved_by' => $admin->id, 'approved_at' => now(), 'notes' => $notes])`.
  5. Catat audit trail: `finance.topup_approved`.

- `rejectTopup(TopupRequest $topup, Admin $admin, string $reason): void`:
  1. Update status `$topup->update(['status' => 'REJECTED', 'rejected_by' => $admin->id, 'rejected_at' => now(), 'rejection_reason' => $reason])`.
  2. Catat audit trail: `finance.topup_rejected`.

- `processAdjustment(User $user, string $type, int $amount, string $reason, Admin $admin): void`:
  1. Validasi `$type` ('CREDIT' atau 'DEBIT').
  2. Eksekusi `WalletService::credit()` atau `WalletService::debit()` dengan tipe mutasi `ADJUSTMENT_CREDIT` / `ADJUSTMENT_DEBIT`.
  3. Catat audit trail lengkap: `finance.balance_adjusted` dengan target `$user->wallet`.

### 4.2 `AdminReconciliationService`
Class `App\Domain\Admin\Services\AdminReconciliationService`:

- `compareProviderBalances(): array`:
  1. Iterasi provider aktif (Digiflazz, IAK, OTOMAX).
  2. Ambil saldo riil live melalui gateway API (`$gateway->checkBalance()`).
  3. Ambil total saldo internal yang tercatat di pembukuan.
  4. Hitung selisih (discrepancy = Live Balance - Internal Recorded Balance).
  5. Tandai status: `MATCH` (selisih 0), `DISCREPANCY` (ada selisih), atau `ERROR` (gagal terhubung ke API provider).

---

## 5. Controllers & Endpoints

### 5.1 `AdminTopupApprovalController`
- `index()`: Daftar request top-up dengan filter status (default: PENDING).
- `show(TopupRequest $topup)`: Detail permohonan top-up, bukti transfer gambar, data rekening bank pengirim.
- `approve(ApproveTopupRequest $request, TopupRequest $topup)`: Eksekusi persetujuan. Dilindungi `password.confirm`.
- `reject(RejectTopupRequest $request, TopupRequest $topup)`: Eksekusi penolakan dengan input alasan.

### 5.2 `AdminWalletAdjustmentController`
- `create()`: Form pencarian user (via phone/email) dan input nominal adjustment + alasan.
- `store(WalletAdjustmentRequest $request)`:
  - Middleware: `password.confirm:admin.password.confirm`
  - Validasi ketat: alasan wajib minimal 15 karakter.
  - Eksekusi penyesuaian via `AdminFinanceService::processAdjustment()`.

### 5.3 `AdminLedgerMutationController`
- `index()`: Filter dan paginasi tabel mutasi saldo pengguna.
- `exportCsv(ExportLedgerCsvRequest $request)`:
  - Mengembalikan `StreamedResponse` dengan header `Content-Type: text/csv`.
  - Menulis baris CSV menggunakan `cursor()` untuk performa tinggi tanpa out-of-memory.

### 5.4 `AdminReconciliationController`
- `index()`: Menampilkan ringkasan rekonsiliasi saldo deposit provider vs catatan pembukuan sistem.

---

## 6. Routing (`routes/admin.php`)

```php
Route::middleware(['auth:admin', 'admin.csp'])->prefix('finance')->name('finance.')->group(function () {
    // Hanya Super Admin & Finance Admin
    Route::middleware(['can:access-finance'])->group(function () {
        // Topup Approvals
        Route::get('topups', [AdminTopupApprovalController::class, 'index'])->name('topups.index');
        Route::get('topups/{topup}', [AdminTopupApprovalController::class, 'show'])->name('topups.show');

        // Ledger & Export
        Route::get('ledger', [AdminLedgerMutationController::class, 'index'])->name('ledger.index');
        Route::get('ledger/export', [AdminLedgerMutationController::class, 'exportCsv'])->name('ledger.export');

        // Rekonsiliasi Provider
        Route::get('reconciliation', [AdminReconciliationController::class, 'index'])->name('reconciliation.index');

        // Aksi Finansial Sensitif (Wajib Konfirmasi Password)
        Route::middleware(['password.confirm:admin.password.confirm'])->group(function () {
            Route::post('topups/{topup}/approve', [AdminTopupApprovalController::class, 'approve'])->name('topups.approve');
            Route::post('topups/{topup}/reject', [AdminTopupApprovalController::class, 'reject'])->name('topups.reject');

            Route::get('adjustments/create', [AdminWalletAdjustmentController::class, 'create'])->name('adjustments.create');
            Route::post('adjustments', [AdminWalletAdjustmentController::class, 'store'])->name('adjustments.store');
        });
    });
});
```

---

## 7. Feature Tests (Coverage Wajib)

File di `tests/Feature/Admin/Finance/`:
1. `AdminTopupApprovalTest.php`:
   - `test_ops_admin_cannot_access_finance_routes()`
   - `test_finance_admin_can_approve_pending_topup()`
   - `test_approve_topup_credits_wallet_via_wallet_service()`
   - `test_concurrent_topup_approval_prevented_by_pessimistic_lock()`
   - `test_reject_topup_updates_status_without_crediting_wallet()`
2. `AdminWalletAdjustmentTest.php`:
   - `test_wallet_adjustment_requires_password_confirmation()`
   - `test_credit_adjustment_increases_user_wallet_balance()`
   - `test_debit_adjustment_decreases_user_wallet_balance()`
   - `test_debit_adjustment_fails_if_balance_insufficient()`
   - `test_adjustment_fails_without_valid_reason()`
   - `test_adjustment_recorded_in_admin_audit_logs()`
3. `AdminLedgerMutationViewTest.php`:
   - `test_finance_admin_can_filter_and_view_wallet_mutations()`
   - `test_streaming_csv_export_returns_valid_csv_headers_and_data()`
4. `AdminReconciliationTest.php`:
   - `test_reconciliation_detects_balance_discrepancy()`
