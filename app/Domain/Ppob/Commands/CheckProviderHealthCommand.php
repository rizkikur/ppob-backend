<?php

namespace App\Domain\Ppob\Commands;

use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Product\Models\Provider;
use Illuminate\Console\Command;

class CheckProviderHealthCommand extends Command
{
    protected $signature = 'ppob:health:check {--strict : Kembalikan status error (exit code 1) jika ada provider yang tidak sehat}';

    protected $description = 'Periksa status kesehatan dan Circuit Breaker seluruh provider PPOB';

    public function handle(CircuitBreakerService $circuitBreaker): int
    {
        $this->info('=== PPOB Provider Health & Circuit Breaker Status ===');

        $providers = Provider::active()->orderBy('priority', 'asc')->get();

        if ($providers->isEmpty()) {
            $this->warn('Tidak ada provider aktif yang ditemukan.');

            return self::SUCCESS;
        }

        $headers = ['Provider', 'Code', 'Driver', 'Queue', 'Circuit State', 'Available?'];
        $rows = [];
        $hasUnhealthy = false;

        foreach ($providers as $provider) {
            $code = (string) $provider->code;
            $state = $circuitBreaker->getState($code);
            $available = $circuitBreaker->isAvailable($code);

            if ($state === CircuitBreakerService::STATE_OPEN || ! $available) {
                $hasUnhealthy = true;
                $stateDisplay = "<fg=red>{$state}</>";
                $availDisplay = '<fg=red>NO</>';
            } elseif ($state === CircuitBreakerService::STATE_HALF_OPEN) {
                $stateDisplay = "<fg=yellow>{$state}</>";
                $availDisplay = '<fg=yellow>TRIAL</>';
            } else {
                $stateDisplay = "<fg=green>{$state}</>";
                $availDisplay = '<fg=green>YES</>';
            }

            $rows[] = [
                $provider->name,
                $provider->code,
                $provider->driver,
                $provider->queue_name ?: 'transactions',
                $stateDisplay,
                $availDisplay,
            ];
        }

        $this->table($headers, $rows);

        if ($hasUnhealthy) {
            $this->warn('PERINGATAN: Terdapat provider yang dalam status gangguan / Circuit Breaker OPEN!');

            if ($this->option('strict')) {
                return self::FAILURE;
            }
        } else {
            $this->info('Semua provider berada dalam kondisi prima (CLOSED / Available).');
        }

        return self::SUCCESS;
    }
}
