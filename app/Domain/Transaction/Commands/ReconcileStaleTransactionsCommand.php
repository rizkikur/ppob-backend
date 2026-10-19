<?php

namespace App\Domain\Transaction\Commands;

use App\Domain\Ppob\Services\PpobService;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Services\OperationalAlertService;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command untuk mendeteksi dan me-resolve transaksi yang stuck di status PROCESSING.
 *
 * Transaksi bisa stuck jika:
 * - CheckTransactionStatusJob habis retry tanpa hasil final
 * - Provider callback/webhook tidak pernah datang (lost webhook)
 * - Queue worker crash setelah markProcessing() tapi sebelum selesai
 *
 * Strategi:
 * 1. Cari transaksi PROCESSING yang sudah lebih tua dari --stale-minutes
 * 2. Untuk setiap transaksi: re-query status ke provider upstream
 * 3. Jika provider merespons final (success/failed): update status
 * 4. Jika masih pending/unknown: alert admin untuk manual review
 */
class ReconcileStaleTransactionsCommand extends Command
{
    protected $signature = 'ppob:transactions:reconcile
                            {--stale-minutes=30 : Usia minimum transaksi PROCESSING yang dianggap stale (menit)}
                            {--auto-resolve : Jika diset, otomatis re-query provider dan update status}
                            {--dry-run : Hanya tampilkan transaksi stale tanpa tindakan}';

    protected $description = 'Deteksi dan resolve transaksi PROCESSING yang stuck (stale reconciliation)';

    public function handle(
        TransactionService $transactionService,
        PpobService $ppobService
    ): int {
        $staleMinutes = (int) $this->option('stale-minutes');
        $autoResolve = (bool) $this->option('auto-resolve');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subMinutes($staleMinutes);

        $staleTransactions = Transaction::with(['product.provider', 'user', 'supplier'])
            ->where('status', Transaction::STATUS_PROCESSING)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('updated_at', 'asc')
            ->limit(100) // Batch limit untuk mencegah overload
            ->get();

        if ($staleTransactions->isEmpty()) {
            $this->info("✅ Tidak ada transaksi PROCESSING yang stale (>{$staleMinutes} menit).");
            return self::SUCCESS;
        }

        $this->warn("⚠️  Ditemukan {$staleTransactions->count()} transaksi stale PROCESSING.");
        $this->newLine();

        $resolved = 0;
        $pendingManual = 0;
        $errors = 0;

        foreach ($staleTransactions as $tx) {
            $ageMinutes = (int) now()->diffInMinutes($tx->updated_at);
            $supplier = $tx->supplier ?? $tx->product?->provider;

            $this->line("  #{$tx->id} | SKU: {$tx->product?->sku_code} | Customer: {$tx->customer_number} | Supplier: {$supplier?->name} | Stale: {$ageMinutes}m");

            if ($dryRun) {
                continue;
            }

            if ($autoResolve && $tx->provider_ref && $tx->product) {
                try {
                    $result = $ppobService->checkStatus($tx->product, (string) $tx->provider_ref, $supplier);
                    $status = $result['status'] ?? 'unknown';

                    if ($status === 'success') {
                        $transactionService->markSuccess(
                            $tx,
                            $tx->provider_ref ?? ($result['provider_ref'] ?? ''),
                            $result['raw'] ?? $result
                        );
                        $this->info("    ✅ Resolved → SUCCESS (provider ref: {$tx->provider_ref})");
                        $resolved++;
                        continue;
                    }

                    if ($status === 'failed') {
                        $reason = $result['message'] ?? 'Reconciliation: provider reported failed';
                        $transactionService->failAndRefund($tx, $reason, $result['raw'] ?? $result);
                        $this->warn("    🔄 Resolved → FAILED + REFUND ({$reason})");
                        $resolved++;
                        continue;
                    }

                    // Status masih pending/unknown
                    $this->comment("    ⏳ Provider masih merespons '{$status}' — perlu review manual");
                    $pendingManual++;
                } catch (\Throwable $e) {
                    $this->error("    ❌ Error re-query: {$e->getMessage()}");
                    $errors++;
                }
            } else {
                $pendingManual++;
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Stale', $staleTransactions->count()],
                ['Auto-Resolved', $resolved],
                ['Pending Manual Review', $pendingManual],
                ['Errors', $errors],
            ]
        );

        // Alert admin jika ada transaksi yang perlu manual review
        if ($pendingManual > 0) {
            Log::warning("Reconciliation: {$pendingManual} transaksi PROCESSING masih stuck setelah re-query.");

            try {
                app(OperationalAlertService::class)->sendAlert(
                    "⏰ [STALE TRANSACTIONS] {$pendingManual} Transaksi Stuck",
                    "<b>{$pendingManual}</b> transaksi masih berstatus PROCESSING setelah >{$staleMinutes} menit.\n"
                    . "Total diperiksa: {$staleTransactions->count()}\n"
                    . "Auto-resolved: {$resolved}\n"
                    . "Silakan periksa di panel admin → Transaksi → Filter: PROCESSING.",
                    'warning',
                    'stale_transactions'
                );
            } catch (\Throwable) {
                // Jangan gagalkan command jika alert error
            }
        }

        return $pendingManual > 0 || $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
