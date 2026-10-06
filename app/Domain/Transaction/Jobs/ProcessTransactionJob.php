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
 * Job async untuk mengirim transaksi ke provider PPOB.
 * Dijalankan di queue per-supplier oleh Laravel Horizon (ADR-002).
 */
class ProcessTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly Transaction $transaction) {}

    public function handle(PpobService $ppobService, TransactionService $transactionService): void
    {
        /** @var Transaction|null $tx */
        $tx = $this->transaction->fresh(['product.provider', 'user']);
        if (! $tx || $tx->status !== Transaction::STATUS_PENDING) {
            return;
        }

        $transactionService->markProcessing($tx);

        try {
            $product = $tx->product;
            $result = $ppobService->pay(
                $product,
                $tx->customer_number,
                $tx->amount_cents,
                (string) $tx->id
            );

            $status = $result['status'] ?? 'failed';

            if ($status === 'success') {
                $transactionService->markSuccess(
                    $tx,
                    $result['provider_ref'] ?? '',
                    $result['raw'] ?? $result
                );
            } elseif ($status === 'pending') {
                $transactionService->markProcessing(
                    $tx,
                    $result['provider_ref'] ?? null,
                    $result['raw'] ?? $result
                );

                CheckTransactionStatusJob::dispatch($tx)->delay(now()->addSeconds(10));
            } else {
                $transactionService->failAndRefund(
                    $tx,
                    $result['message'] ?? 'Provider error',
                    $result['raw'] ?? $result
                );
            }
        } catch (\Throwable $e) {
            $transactionService->failAndRefund($tx, $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        /** @var Transaction|null $tx */
        $tx = $this->transaction->fresh(['user']);
        if ($tx && ! in_array($tx->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_REFUNDED], true)) {
            app(TransactionService::class)->failAndRefund(
                $tx,
                'Job gagal: '.$exception->getMessage()
            );
        }
    }
}
