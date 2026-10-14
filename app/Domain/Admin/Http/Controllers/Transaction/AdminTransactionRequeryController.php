<?php

namespace App\Domain\Admin\Http\Controllers\Transaction;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminTransactionManagementService;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminTransactionRequeryController extends Controller
{
    public function __construct(
        protected AdminTransactionManagementService $transactionService
    ) {}

    /**
     * Requery status from provider and update transaction if needed.
     */
    public function requery(Transaction $transaction, Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        try {
            $result = $this->transactionService->requeryProvider($transaction, $admin);

            $statusText = strtoupper($result['status'] ?? 'UNKNOWN');
            $refText = $result['provider_ref'] ?? '-';

            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->with('success', "Requery ke provider sukses. Status Upstream: {$statusText} (Ref: {$refText}).");
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->with('error', "Gagal melakukan requery ke provider: {$e->getMessage()}");
        }
    }
}
