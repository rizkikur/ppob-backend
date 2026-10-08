<?php

namespace App\Domain\Partner\Commands;

use App\Domain\Partner\Jobs\DeliverWebhookJob;
use App\Domain\Partner\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Command untuk re-dispatch webhook callback mitra yang pending retry (ADR-008).
 * Menjamin pengiriman tetap berjalan jika server/worker sempat restart.
 */
class RetryFailedWebhooksCommand extends Command
{
    protected $signature = 'ppob:webhook:retry {--limit=50 : Batas maksimal record yang diproses}';

    protected $description = 'Re-dispatch pengiriman callback webhook mitra yang siap di-retry';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $deliveries = WebhookDelivery::pendingRetry()
            ->limit($limit)
            ->get();

        if ($deliveries->isEmpty()) {
            $this->info('Tidak ada webhook callback yang perlu di-retry saat ini.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$deliveries->count()} callback siap di-retry. Memulai dispatch...");

        $count = 0;
        foreach ($deliveries as $delivery) {
            DeliverWebhookJob::dispatch($delivery->id);
            $count++;
        }

        $this->info("Berhasil men-dispatch {$count} job callback webhook ke queue.");

        return self::SUCCESS;
    }
}
