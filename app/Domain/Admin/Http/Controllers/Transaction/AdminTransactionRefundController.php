<?php

namespace App\Domain\Admin\Http\Controllers\Transaction;

use App\Domain\Admin\Http\Requests\ManualRefundRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminTransactionManagementService;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AdminTransactionRefundController extends Controller
{
    public function __construct(
        protected AdminTransactionManagementService $transactionService
    ) {}

    /**
     * Process manual refund for a failed or stuck transaction.
     */
    public function refund(Transaction $transaction, ManualRefundRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        try {
            $this->transactionService->processManualRefund(
                transaction: $transaction,
                admin: $admin,
                reason: $request->validated('reason')
            );

            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->with('success', "Manual refund untuk transaksi #{$transaction->id} berhasil dikreditkan ke dompet user.");
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->withErrors($e->errors());
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->with('error', "Gagal memproses refund: {$e->getMessage()}");
        }
    }
}
