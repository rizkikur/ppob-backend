<?php

namespace App\Domain\Transaction\Services;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class TransactionEventStreamer
{
    /**
     * Broadcast transaksi baru atau perubahan status untuk stream SSE realtime.
     */
    public function broadcast(Transaction $transaction, string $action, ?string $oldStatus = null): array
    {
        $transaction->loadMissing(['product.provider', 'supplier']);

        $sellPrice = $transaction->sell_price_cents instanceof Money
            ? $transaction->sell_price_cents->toRupiah()
            : (int) (($transaction->getAttributes()['sell_price_cents'] ?? 0) / 100);

        $seq = (int) Cache::increment('sse:event:seq');

        $event = [
            'seq' => $seq,
            'action' => $action, // 'created' | 'updated'
            'id' => $transaction->id,
            'status' => $transaction->status,
            'old_status' => $oldStatus,
            'customer_number' => $transaction->customer_number,
            'product_id' => $transaction->product_id,
            'product_name' => $transaction->product?->name ?? 'SKU #' . $transaction->product_id,
            'product_sku' => $transaction->product?->sku_code ?? '-',
            'sell_price' => $sellPrice,
            'provider_id' => $transaction->supplier_id ?? $transaction->product?->provider_id,
            'provider_name' => $transaction->supplier?->name ?? $transaction->product?->provider?->name ?? '-',
            'provider_ref' => $transaction->provider_ref ?? '-',
            'is_failover' => (bool) $transaction->is_failover,
            'partner_id' => $transaction->partner_id,
            'failure_reason' => $transaction->failure_reason ?? null,
            'time' => $transaction->created_at ? $transaction->created_at->format('H:i:s') : now()->format('H:i:s'),
            'date' => $transaction->created_at ? $transaction->created_at->format('Y-m-d') : now()->format('Y-m-d'),
            'timestamp' => now()->timestamp,
        ];

        Cache::put('sse:event:latest', $event, 300);

        try {
            Redis::publish('sse:transactions', json_encode($event));
        } catch (\Throwable) {
            // Abaikan jika Redis belum running sebagai daemon
        }

        return $event;
    }

    /**
     * Periksa apakah event transaksi ini cocok dengan parameter filter aktif client.
     */
    public function matchesFilter(array $event, array $filters): bool
    {
        // 1. Filter Status
        if (! empty($filters['status'])) {
            $target = $filters['status'];
            // Cocok jika status sekarang == target, ATAU status sebelumnya == target (agar row pending yang berubah bisa diupdate)
            if ($event['status'] !== $target && ($event['old_status'] ?? '') !== $target) {
                return false;
            }
        }

        // 2. Filter Provider ID
        if (! empty($filters['provider_id'])) {
            if ((string) ($event['provider_id'] ?? '') !== (string) $filters['provider_id']) {
                return false;
            }
        }

        // 3. Filter Failover Route
        if (isset($filters['is_failover']) && $filters['is_failover'] !== '') {
            $isFailover = (int) ($event['is_failover'] ?? 0);
            if ($isFailover !== (int) $filters['is_failover']) {
                return false;
            }
        }

        // 4. Filter Pencarian Bebas (q)
        if (! empty($filters['q'])) {
            $q = strtolower(trim($filters['q']));
            $haystack = strtolower(implode(' ', [
                (string) $event['id'],
                (string) ($event['customer_number'] ?? ''),
                (string) ($event['product_name'] ?? ''),
                (string) ($event['product_sku'] ?? ''),
                (string) ($event['provider_name'] ?? ''),
                (string) ($event['provider_ref'] ?? ''),
            ]));

            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        // 5. Filter Rentang Tanggal
        if (! empty($filters['date_start']) && ($event['date'] ?? '') < $filters['date_start']) {
            return false;
        }

        if (! empty($filters['date_end']) && ($event['date'] ?? '') > $filters['date_end']) {
            return false;
        }

        return true;
    }
}
