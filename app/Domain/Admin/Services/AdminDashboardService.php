<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Services\TransactionMetricService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AdminDashboardService
{
    public function __construct(
        protected CircuitBreakerService $circuitBreakerService,
        protected TransactionMetricService $metricService
    ) {}

    /**
     * Get aggregated KPI summary metrics based on admin role.
     *
     * @return array<string, mixed>
     */
    public function getSummaryMetrics(AdminRole $role): array
    {
        $cacheKey = "admin:dashboard:metrics:{$role->value}";

        return Cache::remember($cacheKey, 30, function () use ($role) {
            return $this->metricService->getSummaryMetrics($role);
        });
    }

    /**
     * Get provider status and circuit breaker health summary.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProviderHealthSummary(): array
    {
        return Cache::remember('admin:dashboard:providers', 10, function () {
            $providers = Provider::all();

            $summary = [];
            foreach ($providers as $provider) {
                $cbState = $this->circuitBreakerService->getState($provider->code);

                $healthStatus = match ($cbState) {
                    CircuitBreakerService::STATE_CLOSED => 'NORMAL',
                    CircuitBreakerService::STATE_HALF_OPEN => 'DEGRADED',
                    CircuitBreakerService::STATE_OPEN => 'TRIPPED',
                    default => 'NORMAL',
                };

                $summary[] = [
                    'id' => $provider->id,
                    'name' => $provider->name,
                    'code' => $provider->code,
                    'is_active' => $provider->is_active,
                    'circuit_breaker_state' => $cbState,
                    'status' => $healthStatus,
                    'threshold_cents' => $provider->balance_alert_threshold_cents,
                ];
            }

            return $summary;
        });
    }

    /**
     * Get hourly transaction trend for the past 24 hours.
     *
     * @return array{labels: array<int, string>, success: array<int, int>, failed: array<int, int>}
     */
    public function getHourlyTrend24Hours(): array
    {
        return Cache::remember('admin:dashboard:trend_24h', 60, function () {
            return $this->metricService->getHourlyTrend24Hours();
        });
    }

    /**
     * Clear all cached dashboard statistics.
     */
    public function clearDashboardCache(): void
    {
        foreach (AdminRole::cases() as $role) {
            Cache::forget("admin:dashboard:metrics:{$role->value}");
        }
        Cache::forget('admin:dashboard:providers');
        Cache::forget('admin:dashboard:trend_24h');

        try {
            Cache::tags(['admin', 'dashboard'])->flush();
        } catch (\Throwable) {
            // Ignore if driver doesn't support tags
        }
    }
}
