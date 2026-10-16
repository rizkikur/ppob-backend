<?php

namespace App\Domain\Transaction\Observers;

use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionMetricService;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        app(TransactionMetricService::class)->recordCreated($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        app(TransactionMetricService::class)->recordUpdated($transaction);
    }
}
