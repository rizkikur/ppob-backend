<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Admin;
use App\Domain\Partner\Jobs\DeliverWebhookJob;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTransactionManagementService
{
    public function __construct(
        protected WalletService $walletService,
        protected AdminAuditLogService $auditLogService,
        protected ?PpobService $ppobService = null
    ) {}

    private function getPpobService(): PpobService
    {
        return $this->ppobService ?? app(PpobService::class);
    }

    /**
     * Build base query with all essential debug relations and filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(array $filters)
    {
        $query = Transaction::query()->with([
            'user.userTier',
            'product.provider',
            'product.category',
            'partner',
            'supplier',
            'originalSupplier',
            'inquiry',
        ]);

        // Search Keyword (ID, customer number, provider ref, idempotency key)
        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $query->where(function ($sub) use ($q) {
                if (is_numeric($q)) {
                    $sub->orWhere('id', (int) $q);
                    // Jika nomor HP Indonesia (awalan 08 atau 628), utamakan prefix match agar B-Tree index terpakai
                    if (str_starts_with($q, '08') || str_starts_with($q, '628')) {
                        $sub->orWhere('customer_number', 'like', "{$q}%");
                    } else {
                        $sub->orWhere('customer_number', 'like', "%{$q}%");
                    }
                } else {
                    $sub->orWhere('customer_number', 'like', "%{$q}%");
                }

                // Jika format idempotency key / UUID, exact match ke unique index
                if (str_starts_with($q, 'DEMO-IDEMP-') || str_starts_with($q, 'idem-') || strlen($q) === 36) {
                    $sub->orWhere('idempotency_key', $q);
                } else {
                    $sub->orWhere('idempotency_key', 'like', "%{$q}%");
                }

                $sub->orWhere('provider_ref', 'like', "%{$q}%");
            });
        }

        // Status Filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Provider Filter
        if (! empty($filters['provider_id'])) {
            $providerId = (int) $filters['provider_id'];
            $query->where(function ($sub) use ($providerId) {
                $sub->where('supplier_id', $providerId)
                    ->orWhereHas('product', fn ($p) => $p->where('provider_id', $providerId));
            });
        }

        // Failover Route Filter
        if (isset($filters['is_failover']) && $filters['is_failover'] !== '') {
            $query->where('is_failover', (bool) $filters['is_failover']);
        }

        // Partner B2B Filter
        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', (int) $filters['partner_id']);
        }

        // Date Range Filter
        if (! empty($filters['date_start']) && ! empty($filters['date_end'])) {
            $start = min($filters['date_start'], $filters['date_end']);
            $end = max($filters['date_start'], $filters['date_end']);
            $query->whereDate('created_at', '>=', $start)
                ->whereDate('created_at', '<=', $end);
        } elseif (! empty($filters['date_start'])) {
            $query->whereDate('created_at', '>=', $filters['date_start']);
        } elseif (! empty($filters['date_end'])) {
            $query->whereDate('created_at', '<=', $filters['date_end']);
        }

        return $query->latest('created_at');
    }

    /**
     * Filter and paginate transactions with related relations.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filterTransactions(array $filters): LengthAwarePaginator
    {
        return $this->buildQuery($filters)->paginate(25)->withQueryString();
    }

    /**
     * Export filtered transactions into streaming CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportTransactionsCsv(array $filters): StreamedResponse
    {
        $filename = 'transaksi-ppob-'.now()->format('Ymd-His').'.csv';
        $query = $this->buildQuery($filters);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID Transaksi',
                'Tanggal',
                'Waktu',
                'Nama User',
                'No HP User',
                'Tier User',
                'Produk',
                'Kode SKU',
                'No Tujuan / Pelanggan',
                'Modal Upstream (Rp)',
                'Harga Jual (Rp)',
                'Margin Laba (Rp)',
                'Status',
                'Provider Eksekusi',
                'Ref Provider',
                'Serial Number / Token PLN',
                'Rute Failover',
                'Mitra B2B',
                'Idempotency Key',
                'Alasan Gagal / Catatan',
            ]);

            $query->chunk(200, function ($transactions) use ($handle) {
                foreach ($transactions as $trx) {
                    $sn = $trx->provider_response['sn'] ?? '-';
                    $baseCost = $trx->amount_cents ? $trx->amount_cents->toRupiah() : 0;
                    $sellPrice = $trx->sell_price_cents ? $trx->sell_price_cents->toRupiah() : 0;
                    $margin = $sellPrice - $baseCost;

                    fputcsv($handle, [
                        $trx->id,
                        $trx->created_at?->format('Y-m-d'),
                        $trx->created_at?->format('H:i:s'),
                        $trx->user?->name ?? 'Guest',
                        $trx->user?->phone ?? '-',
                        $trx->user?->userTier?->label ?? $trx->user?->userTier?->name ?? '-',
                        $trx->product?->name ?? 'SKU #'.$trx->product_id,
                        $trx->product?->sku_code ?? '-',
                        $trx->customer_number,
                        $baseCost,
                        $sellPrice,
                        $margin,
                        strtoupper($trx->status),
                        $trx->product?->provider?->name ?? $trx->supplier?->name ?? '-',
                        $trx->provider_ref ?? '-',
                        $sn,
                        $trx->is_failover ? 'YA (Failover)' : 'TIDAK (Primary)',
                        $trx->partner?->name ?? '-',
                        $trx->idempotency_key,
                        $trx->failure_reason ?? '-',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Re-dispatch webhook delivery to B2B partner for this transaction.
     */
    public function retryWebhook(Transaction $transaction, Admin $admin): WebhookDelivery
    {
        $transaction->loadMissing(['partner', 'product']);

        if (! $transaction->partner) {
            throw new \RuntimeException('Transaksi ini bukan berasal dari mitra B2B (tidak memiliki webhook callback).');
        }

        $delivery = WebhookDelivery::firstOrCreate(
            [
                'partner_id' => $transaction->partner_id,
                'transaction_id' => $transaction->id,
            ],
            [
                'callback_url' => $transaction->partner->callback_url ?? '',
                'status' => 'pending',
                'attempt' => 1,
            ]
        );

        // Reset status to pending so job will process it
        $delivery->update([
            'status' => 'pending',
            'response_code' => null,
            'response_body' => null,
            'next_retry_at' => now(),
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        $this->auditLogService->log(
            admin: $admin,
            action: 'transaction.webhook_retry',
            target: $transaction,
            changes: ['delivery_id' => $delivery->id],
            reason: "Manual retry webhook callback ke mitra {$transaction->partner->name}"
        );

        return $delivery;
    }

    /**
     * Requery transaction status from upstream provider.
     *
     * @return array<string, mixed>
     */
    public function requeryProvider(Transaction $transaction, Admin $admin): array
    {
        $transaction->loadMissing(['product.provider', 'user']);

        $ref = (string) ($transaction->provider_ref ?? $transaction->id);
        $result = $this->getPpobService()->checkStatus($transaction->product, $ref);

        $providerStatus = $result['status'] ?? 'pending';
        $oldStatus = $transaction->status;

        if ($providerStatus === 'success' && $transaction->status !== Transaction::STATUS_SUCCESS) {
            $transaction->update([
                'status' => Transaction::STATUS_SUCCESS,
                'provider_ref' => $result['provider_ref'] ?? $transaction->provider_ref,
                'provider_response' => $result['raw'] ?? $result,
            ]);
        } elseif ($providerStatus === 'failed' && ! in_array($transaction->status, [Transaction::STATUS_FAILED, Transaction::STATUS_REFUNDED], true)) {
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
                'failure_reason' => $result['message'] ?? 'Provider reported transaction failed',
                'provider_response' => $result['raw'] ?? $result,
            ]);
        }

        $this->auditLogService->log(
            admin: $admin,
            action: 'transaction.requery',
            target: $transaction,
            changes: [
                'status_before' => $oldStatus,
                'status_after' => $transaction->status,
                'provider_result' => $result,
            ],
            reason: "Manual requery ke provider untuk transaksi #{$transaction->id}"
        );

        return $result;
    }

    /**
     * Process manual refund back to user wallet.
     *
     * @throws ValidationException
     */
    public function processManualRefund(Transaction $transaction, Admin $admin, string $reason): void
    {
        DB::transaction(function () use ($transaction, $admin, $reason) {
            /** @var Transaction $locked */
            $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Transaction::STATUS_REFUNDED) {
                throw ValidationException::withMessages([
                    'refund' => 'Transaksi sudah pernah direfund sebelumnya.',
                ]);
            }

            // Check existing refund mutation in wallet ledger
            $alreadyRefunded = WalletMutation::where('reference_type', WalletMutation::REF_REFUND)
                ->where('reference_id', $locked->id)
                ->exists();

            if ($alreadyRefunded) {
                throw ValidationException::withMessages([
                    'refund' => 'Mutasi refund untuk transaksi ini sudah tercatat di sistem.',
                ]);
            }

            // Only failed or stuck processing transactions can be refunded
            if (! in_array($locked->status, [Transaction::STATUS_FAILED, Transaction::STATUS_PROCESSING, Transaction::STATUS_PENDING], true)) {
                throw ValidationException::withMessages([
                    'refund' => 'Hanya transaksi yang gagal atau processing yang dapat direfund.',
                ]);
            }

            $oldStatus = $locked->status;

            // Credit wallet via WalletService
            $this->walletService->credit(
                user: $locked->user,
                amount: $locked->sell_price_cents,
                referenceType: WalletMutation::REF_REFUND,
                referenceId: $locked->id,
                note: "Manual refund transaksi #{$locked->id}. Alasan: {$reason}"
            );

            // Update status to REFUNDED
            $locked->update([
                'status' => Transaction::STATUS_REFUNDED,
                'failure_reason' => $locked->failure_reason
                    ? "{$locked->failure_reason} | Refund: {$reason}"
                    : "Manual Refund: {$reason}",
            ]);

            // Record audit log
            $this->auditLogService->log(
                admin: $admin,
                action: 'transaction.manual_refund',
                target: $locked,
                changes: [
                    'status_before' => $oldStatus,
                    'status_after' => Transaction::STATUS_REFUNDED,
                    'refunded_amount_cents' => $locked->sell_price_cents->toCents(),
                ],
                reason: $reason
            );
        });
    }

    /**
     * Manually override transaction status.
     */
    public function overrideStatus(Transaction $transaction, Admin $admin, string $newStatus, string $reason): void
    {
        DB::transaction(function () use ($transaction, $admin, $newStatus, $reason) {
            /** @var Transaction $locked */
            $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $newStatus) {
                return;
            }

            $oldStatus = $locked->status;

            $locked->update([
                'status' => $newStatus,
                'failure_reason' => $newStatus === Transaction::STATUS_FAILED ? $reason : $locked->failure_reason,
            ]);

            $this->auditLogService->log(
                admin: $admin,
                action: 'transaction.status_override',
                target: $locked,
                changes: [
                    'status_before' => $oldStatus,
                    'status_after' => $newStatus,
                ],
                reason: $reason
            );
        });
    }

    /**
     * Mask sensitive credentials from provider request/response payload.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function maskSensitivePayload(?array $payload): ?array
    {
        if (! $payload) {
            return null;
        }

        $sensitiveKeys = ['pin', 'password', 'secret', 'api_key', 'token', 'signature', 'auth'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key)) {
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains(strtolower($key), $sensitive)) {
                        $value = '******';
                        break;
                    }
                }
            }
        });

        return $payload;
    }
}
