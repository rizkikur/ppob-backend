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
                $supplier = $tx->supplier_id ? \App\Domain\Product\Models\Provider::find($tx->supplier_id) : $tx->product->provider;
                $upstreamRc = $result['code'] ?? $result['response_code'] ?? $result['rc'] ?? null;
                $resolvedError = app(\App\Domain\Ppob\Services\ProviderErrorMappingService::class)->resolve(
                    $supplier,
                    $upstreamRc,
                    $result['message'] ?? 'Provider transaction failed'
                );

                $transactionService->failAndRefund(
                    $tx,
                    $resolvedError['message'],
                    is_array($result['raw'] ?? null)
                        ? array_merge($result['raw'], ['internal_error_code' => $resolvedError['internal_code'], 'upstream_rc' => $upstreamRc])
                        : ['raw' => $result, 'internal_error_code' => $resolvedError['internal_code'], 'upstream_rc' => $upstreamRc]
                );
            } else {
                // Masih pending / processing
                if ($this->attempts() < $this->tries) {
                    $delay = match ($this->attempts()) {
                        1 => 10, // Attempt 1 selesai di detik ke-2 -> jeda 10 detik
                        2 => 15, // Attempt 2 selesai -> jeda 15 detik
                        3 => 30, // Attempt 3 selesai -> jeda 30 detik (kelipatan 15)
                        4 => 45, // Attempt 4 selesai -> jeda 45 detik (kelipatan 15)
                        default => 15,
                    };
                    $this->release($delay);
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
        // Jika 5 kali percobaan polling selesai dan status upstream masih belum sukses/gagal,
        // pertahankan status PROCESSING agar admin dapat melakukan 'Re-query Upstream'
        // atau menunggu penyelesaian webhook/rekonsiliasi resmi dari provider tanpa refund prematur.
        \Illuminate\Support\Facades\Log::warning("Polling status 5x selesai untuk transaksi #{$this->transaction->id}, transaksi tetap PROCESSING: {$exception->getMessage()}");
    }
}
