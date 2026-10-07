<?php

namespace App\Domain\Ppob\Commands;

use App\Domain\Product\Models\Provider;
use Illuminate\Console\Command;

class InspectQueueCommand extends Command
{
    protected $signature = 'ppob:queue:status';

    protected $description = 'Periksa status pemetaan queue worker per supplier sesuai ADR-002';

    public function handle(): int
    {
        $this->info('=== PPOB Supplier Queue Worker Configuration (ADR-002) ===');

        $providers = Provider::orderBy('priority', 'asc')->get();

        if ($providers->isEmpty()) {
            $this->warn('Belum ada provider yang terdaftar di database.');

            return self::SUCCESS;
        }

        $horizonConfig = config('horizon.environments.production', []);
        $headers = ['Code', 'Name', 'Driver', 'Queue Name', 'Max Workers', 'Rate Limit/m', 'Timeout (s)', 'Priority', 'Horizon Config'];
        $rows = [];
        $totalWorkers = 0;

        foreach ($providers as $provider) {
            $queueName = $provider->queue_name ?: 'transactions';
            $horizonKey = 'supervisor-'.$provider->code;
            $hasHorizon = isset($horizonConfig[$horizonKey]) ? 'Configured' : 'Missing';

            $rows[] = [
                $provider->code,
                $provider->name,
                $provider->driver,
                $queueName,
                $provider->max_workers,
                $provider->rate_limit_per_minute,
                $provider->timeout_seconds,
                $provider->priority,
                $hasHorizon,
            ];

            $totalWorkers += (int) $provider->max_workers;
        }

        $this->table($headers, $rows);

        $this->info("Total Alokasi Workers: {$totalWorkers} processes across all suppliers");
        $this->line('');

        // Tampilkan info antrean default
        $this->comment('Default & Failover Queue:');
        $this->line('- Queue: "default", "transactions"');
        $this->line('- Supervisor: supervisor-default (10 processes)');

        return self::SUCCESS;
    }
}
