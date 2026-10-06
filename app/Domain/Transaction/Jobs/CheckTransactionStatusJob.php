<?php

namespace App\Domain\Transaction\Jobs;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Job untuk polling status transaksi ke provider (untuk provider yang async). */
class CheckTransactionStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly Transaction $transaction) {}

    public function handle(): void
    {
        // TODO: Implementasi di Phase 6
    }
}
