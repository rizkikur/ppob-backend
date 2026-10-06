<?php

namespace App\Domain\Transaction\Jobs;

use App\Domain\Ppob\Services\PpobService;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job untuk polling status transaksi ke provider (untuk provider async).
 */
class CheckTransactionStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly Transaction $transaction) {}

    public function handle(PpobService $ppobService, TransactionService $transactionService): void
    {
        /** @var Transaction|null $tx */
        $tx = $this->transaction->fresh(['product.provider', 'user']);
        if (! $tx || $tx->status !== Transaction::STATUS_PROCESSING) {
            return;
        }

        try {
            $result = $ppobService->checkStatus($tx->product, (string) $tx->provider_ref);

            $status = $result['status'] ?? 'pending';

            if ($status === 'success') {
                $transactionService->markSuccess(
                    $tx,
                    $tx->provider_ref ?? ($result['provider_ref'] ?? ''),
                    $result['raw'] ?? $result
                );
            } elseif ($status === 'failed') {
                $transactionService->failAndRefund(
                    $tx,
                    $result['message'] ?? 'Provider transaction failed',
                    $result['raw'] ?? $result
                );
            } else {
                // Masih pending / processing
                if ($this->attempts() < $this->tries) {
                    $this->release(15);
                }
            }
        } catch (\Throwable $e) {
            if ($this->attempts() < $this->tries) {
                $this->release(15);
            } else {
                throw $e;
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        /** @var Transaction|null $tx */
        $tx = $this->transaction->fresh(['user']);
        if ($tx && ! in_array($tx->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_REFUNDED], true)) {
            app(TransactionService::class)->failAndRefund(
                $tx,
                'Polling gagal: '.$exception->getMessage()
            );
        }
    }
}
