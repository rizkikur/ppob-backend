<?php

namespace App\Domain\Admin\Jobs;

use App\Domain\Admin\Models\TransactionExportJob;
use App\Domain\Admin\Services\AdminTransactionManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah detik job boleh berjalan sebelum timeout.
     */
    public int $timeout = 600;

    /**
     * Jumlah percobaan eksekusi jika gagal.
     */
    public int $tries = 2;

    public function __construct(
        public TransactionExportJob $exportJob
    ) {}

    public function handle(AdminTransactionManagementService $transactionService): void
    {
        $this->exportJob->update([
            'status' => TransactionExportJob::STATUS_PROCESSING,
        ]);

        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');

        $relativePath = 'exports/' . $this->exportJob->id . '_' . $this->exportJob->filename;
        $fullPath = $disk->path($relativePath);

        $handle = @fopen($fullPath, 'w');
        if (! $handle) {
            $this->exportJob->update([
                'status' => TransactionExportJob::STATUS_FAILED,
                'error_message' => 'Gagal membuat file export di storage server.',
            ]);
            return;
        }

        try {
            // Tulis UTF-8 BOM untuk kompatibilitas Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Tulis Header Kolom
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

            $filters = $this->exportJob->filters ?? [];
            $query = $transactionService->buildQuery($filters);

            // Hitung total data untuk tracking progress
            $totalRows = (clone $query)->count();
            $this->exportJob->update(['total_rows' => $totalRows]);

            $processed = 0;

            $query->chunk(500, function ($transactions) use ($handle, &$processed) {
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
                        $trx->product?->name ?? 'SKU #' . $trx->product_id,
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

                    $processed++;
                }

                $this->exportJob->update(['processed_rows' => $processed]);
            });

            fclose($handle);

            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            $this->exportJob->update([
                'status' => TransactionExportJob::STATUS_COMPLETED,
                'file_path' => $relativePath,
                'file_size_bytes' => $fileSize,
                'processed_rows' => $processed,
                'completed_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            Log::error('ExportTransactionsJob failed: ' . $e->getMessage(), [
                'job_id' => $this->exportJob->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->exportJob->update([
                'status' => TransactionExportJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
