<?php

namespace App\Domain\Transaction\Observers;

use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionEventStreamer;
use App\Domain\Transaction\Services\TransactionMetricService;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        app(TransactionMetricService::class)->recordCreated($transaction);
        app(TransactionEventStreamer::class)->broadcast($transaction, 'created');
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $oldStatus = $transaction->getOriginal('status');
        app(TransactionMetricService::class)->recordUpdated($transaction);

        if ($transaction->wasChanged('status')) {
            app(TransactionEventStreamer::class)->broadcast($transaction, 'updated', $oldStatus);
        }
    }
}
