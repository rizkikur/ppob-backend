<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\TopupRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function __construct(
        protected CircuitBreakerService $circuitBreakerService
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
            $today = Carbon::today();

            // Total volume and counts
            $todayTransactions = Transaction::whereDate('created_at', $today);
            $totalCount = (clone $todayTransactions)->count();
            $successCount = (clone $todayTransactions)->where('status', Transaction::STATUS_SUCCESS)->count();
            $failedCount = (clone $todayTransactions)->where('status', Transaction::STATUS_FAILED)->count();

            // Success GMV
            $gmvCents = (int) (clone $todayTransactions)
                ->where('status', Transaction::STATUS_SUCCESS)
                ->sum('sell_price_cents');
            $gmvRupiah = (int) ($gmvCents / 100);

            // Gross Profit (Only for Super Admin & Finance Admin)
            $profitRupiah = null;
            if ($role === AdminRole::SUPER_ADMIN || $role === AdminRole::FINANCE_ADMIN) {
                $profitCents = (int) (clone $todayTransactions)
                    ->where('status', Transaction::STATUS_SUCCESS)
                    ->sum(DB::raw('sell_price_cents - amount_cents'));
                $profitRupiah = (int) ($profitCents / 100);
            }

            // Success Rate %
            $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0.0;

            // Pending action queues
            $pendingTransactions = Transaction::whereIn('status', [
                Transaction::STATUS_PENDING,
                Transaction::STATUS_PROCESSING,
            ])->count();

            $pendingTopups = TopupRequest::where('status', TopupRequest::STATUS_PENDING)->count();

            return [
                'gmv_today' => $gmvRupiah,
                'profit_today' => $profitRupiah,
                'transactions_count' => $totalCount,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'success_rate' => $successRate,
                'pending_transactions' => $pendingTransactions,
                'pending_topups' => $pendingTopups,
            ];
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
            $labels = [];
            $successData = [];
            $failedData = [];

            $now = Carbon::now();
            $hours = [];

            for ($i = 23; $i >= 0; $i--) {
                $hourTime = (clone $now)->subHours($i);
                $key = $hourTime->format('Y-m-d H:00:00');
                $labels[] = $hourTime->format('H:00');
                $hours[$key] = [
                    'start' => (clone $hourTime)->startOfHour(),
                    'end' => (clone $hourTime)->endOfHour(),
                    'success' => 0,
                    'failed' => 0,
                ];
            }

            // Group transactions by hour
            $earliest = (clone $now)->subHours(23)->startOfHour();
            $transactions = Transaction::where('created_at', '>=', $earliest)
                ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED])
                ->get(['status', 'created_at']);

            foreach ($transactions as $trx) {
                $trxHour = $trx->created_at->format('Y-m-d H:00:00');
                if (isset($hours[$trxHour])) {
                    if ($trx->status === Transaction::STATUS_SUCCESS) {
                        $hours[$trxHour]['success']++;
                    } elseif ($trx->status === Transaction::STATUS_FAILED) {
                        $hours[$trxHour]['failed']++;
                    }
                }
            }

            foreach ($hours as $item) {
                $successData[] = $item['success'];
                $failedData[] = $item['failed'];
            }

            return [
                'labels' => $labels,
                'success' => $successData,
                'failed' => $failedData,
            ];
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
