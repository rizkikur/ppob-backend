<?php

namespace App\Domain\Transaction\Jobs;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job async untuk mengirim transaksi ke provider PPOB.
 * Dijalankan di queue 'transactions' oleh Laravel Horizon.
 * TODO: Implementasi logika kirim ke provider di Phase 6.
 */
class ProcessTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly Transaction $transaction) {}

    public function handle(): void
    {
        // TODO: Implementasi di Phase 6
        // 1. Resolve driver dari transaction->product->provider
        // 2. Kirim ke provider (PpobService->pay())
        // 3. Update transaction status
        // 4. Jika provider async, dispatch CheckTransactionStatusJob
    }

    public function failed(\Throwable $exception): void
    {
        $this->transaction->update(['status' => Transaction::STATUS_FAILED, 'failure_reason' => $exception->getMessage()]);
    }
}
