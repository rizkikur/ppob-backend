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
        $tx = $this->transaction->fresh(['product.provider', 'user', 'supplier']);
        if (! $tx || $tx->status !== Transaction::STATUS_PENDING) {
            return;
        }

        $transactionService->markProcessing($tx);

        try {
            $product = $tx->product;
            $supplier = $tx->supplier ?? $product?->provider;
            $result = $ppobService->pay(
                $product,
                $tx->customer_number,
                $tx->amount_cents,
                (string) $tx->id,
                $supplier
            );

            $status = $result['status'] ?? 'failed';

            // Deteksi kenaikan harga modal dari upstream (Point 2 user request)
            $upstreamCostCents = $result['actual_price_cents']
                ?? $result['cost_cents']
                ?? (isset($result['raw']['price']) ? (int) round((float) $result['raw']['price'] * 100) : null)
                ?? (isset($result['raw']['buyer_last_price']) ? (int) round((float) $result['raw']['buyer_last_price'] * 100) : null);

            if ($upstreamCostCents !== null && $product) {
                $currentBaseCents = $product->base_price_cents?->toCents() ?? 0;
                if ($upstreamCostCents > $currentBaseCents) {
                    // Modal upstream naik! Update HPP modal produk agar tidak berulang
                    $product->update([
                        'base_price_cents' => $upstreamCostCents,
                    ]);

                    // Jika harga modal baru melebihi harga jual transaksi (rugi) atau naik:
                    // Segera nonaktifkan (close) produk agar kerugian terjadi 1x saja & admin bisa sosialisasi ke klien
                    if ($upstreamCostCents >= ($tx->sell_price_cents?->toCents() ?? 0) || config('ppob.auto_freeze_on_price_hike', true)) {
                        $product->update(['is_active' => false]);
                        \Illuminate\Support\Facades\Log::warning("Produk {$product->sku_code} dinonaktifkan otomatis karena kenaikan modal upstream ({$currentBaseCents} -> {$upstreamCostCents})");

                        // Alert ke admin via Telegram/email agar segera ditindaklanjuti
                        try {
                            app(\App\Domain\Shared\Services\OperationalAlertService::class)->notifyProductAutoFrozen(
                                $product->sku_code,
                                $product->name,
                                $currentBaseCents,
                                $upstreamCostCents,
                                $tx->sell_price_cents?->toCents() ?? 0
                            );
                        } catch (\Throwable) {
                            // Jangan ganggu flow transaksi jika alert gagal
                        }
                    }
                }
            }

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

                // Polling logic per user request (Point 3):
                // Jika supplier memiliki callback/webhook, tunggu callback (tanpa aggressive auto-poll).
                // Jika supplier TIDAK memiliki callback, lakukan polling otomatis dimulai pada detik ke-2!
                if (! $supplier?->supportsCallback()) {
                    CheckTransactionStatusJob::dispatch($tx)->delay(now()->addSeconds(2));
                }
            } else {
                $upstreamRc = $result['code'] ?? $result['response_code'] ?? $result['rc'] ?? null;
                $resolvedError = app(\App\Domain\Ppob\Services\ProviderErrorMappingService::class)->resolve(
                    $supplier,
                    $upstreamRc,
                    $result['message'] ?? 'Provider error'
                );

                $transactionService->failAndRefund(
                    $tx,
                    $resolvedError['message'],
                    is_array($result['raw'] ?? null)
                        ? array_merge($result['raw'], ['internal_error_code' => $resolvedError['internal_code'], 'upstream_rc' => $upstreamRc])
                        : ['raw' => $result, 'internal_error_code' => $resolvedError['internal_code'], 'upstream_rc' => $upstreamRc]
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
