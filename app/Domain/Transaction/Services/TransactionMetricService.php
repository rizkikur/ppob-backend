<?php

namespace App\Domain\Transaction\Services;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\DailyTransactionMetric;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\TopupRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TransactionMetricService
{
    /**
     * Catat metrik saat transaksi baru dibuat.
     */
    public function recordCreated(Transaction $transaction): void
    {
        $date = $transaction->created_at ? $transaction->created_at->toDateString() : now()->toDateString();
        $hour = $transaction->created_at ? (int) $transaction->created_at->hour : (int) now()->hour;

        $sellPriceCents = $this->extractCents($transaction, 'sell_price_cents');
        $amountCents = $this->extractCents($transaction, 'amount_cents');
        $profitCents = $sellPriceCents - $amountCents;

        $isSuccess = $transaction->status === Transaction::STATUS_SUCCESS;
        $isFailed = $transaction->status === Transaction::STATUS_FAILED;
        $isPending = in_array($transaction->status, [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING], true);

        $this->upsertMetric(
            date: $date,
            hour: $hour,
            totalDelta: 1,
            successDelta: $isSuccess ? 1 : 0,
            failedDelta: $isFailed ? 1 : 0,
            pendingDelta: $isPending ? 1 : 0,
            gmvCentsDelta: $isSuccess ? $sellPriceCents : 0,
            costCentsDelta: $isSuccess ? $amountCents : 0,
            profitCentsDelta: $isSuccess ? $profitCents : 0,
        );

        $this->incrementAtomicCounters(
            date: $date,
            hour: $hour,
            totalDelta: 1,
            successDelta: $isSuccess ? 1 : 0,
            failedDelta: $isFailed ? 1 : 0,
            gmvCentsDelta: $isSuccess ? $sellPriceCents : 0,
            profitCentsDelta: $isSuccess ? $profitCents : 0,
        );
    }

    /**
     * Catat metrik saat status transaksi berubah (misal: pending -> success atau pending -> failed).
     */
    public function recordUpdated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('status')) {
            return;
        }

        $date = $transaction->created_at ? $transaction->created_at->toDateString() : now()->toDateString();
        $hour = $transaction->created_at ? (int) $transaction->created_at->hour : (int) now()->hour;

        $oldStatus = $transaction->getOriginal('status');
        $newStatus = $transaction->status;

        $sellPriceCents = $this->extractCents($transaction, 'sell_price_cents');
        $amountCents = $this->extractCents($transaction, 'amount_cents');
        $profitCents = $sellPriceCents - $amountCents;

        $successDelta = 0;
        $failedDelta = 0;
        $pendingDelta = 0;
        $gmvDelta = 0;
        $costDelta = 0;
        $profitDelta = 0;

        if ($oldStatus !== Transaction::STATUS_SUCCESS && $newStatus === Transaction::STATUS_SUCCESS) {
            $successDelta = 1;
            $gmvDelta = $sellPriceCents;
            $costDelta = $amountCents;
            $profitDelta = $profitCents;

            if (in_array($oldStatus, [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING], true)) {
                $pendingDelta = -1;
            }
        } elseif ($oldStatus !== Transaction::STATUS_FAILED && $newStatus === Transaction::STATUS_FAILED) {
            $failedDelta = 1;
            if (in_array($oldStatus, [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING], true)) {
                $pendingDelta = -1;
            }
        }

        if ($successDelta !== 0 || $failedDelta !== 0 || $pendingDelta !== 0) {
            $this->upsertMetric(
                date: $date,
                hour: $hour,
                totalDelta: 0,
                successDelta: $successDelta,
                failedDelta: $failedDelta,
                pendingDelta: $pendingDelta,
                gmvCentsDelta: $gmvDelta,
                costCentsDelta: $costDelta,
                profitCentsDelta: $profitDelta,
            );

            $this->incrementAtomicCounters(
                date: $date,
                hour: $hour,
                totalDelta: 0,
                successDelta: $successDelta,
                failedDelta: $failedDelta,
                gmvCentsDelta: $gmvDelta,
                profitCentsDelta: $profitDelta,
            );
        }
    }

    /**
     * Hitung ringkasan metrik KPI untuk dashboard admin.
     * Menggunakan Redis/Cache atau tabel daily_transaction_metrics sehingga
     * tidak melakukan full table scan pada tabel transactions.
     *
     * @return array<string, mixed>
     */
    public function getSummaryMetrics(AdminRole $role, ?Carbon $date = null): array
    {
        $targetDate = $date ?? Carbon::today();
        $dateStr = $targetDate->toDateString();

        // 1. Ambil agregat dari tabel daily_transaction_metrics untuk hari ini
        $agg = DailyTransactionMetric::where('metric_date', $dateStr)
            ->selectRaw('
                COALESCE(SUM(total_count), 0) as total_count,
                COALESCE(SUM(success_count), 0) as success_count,
                COALESCE(SUM(failed_count), 0) as failed_count,
                COALESCE(SUM(gmv_cents), 0) as gmv_cents,
                COALESCE(SUM(profit_cents), 0) as profit_cents
            ')
            ->first();

        $totalCount = (int) ($agg->total_count ?? 0);
        $successCount = (int) ($agg->success_count ?? 0);
        $failedCount = (int) ($agg->failed_count ?? 0);
        $gmvCents = (int) ($agg->gmv_cents ?? 0);
        $profitCents = (int) ($agg->profit_cents ?? 0);

        // Fallback jika belum pernah ada agregat (misal data transaksi dibuat sebelum fitur aktif / testing seeder)
        if ($totalCount === 0 && Transaction::whereDate('created_at', $targetDate)->exists()) {
            $todayTransactions = Transaction::whereDate('created_at', $targetDate);
            $totalCount = (clone $todayTransactions)->count();
            $successCount = (clone $todayTransactions)->where('status', Transaction::STATUS_SUCCESS)->count();
            $failedCount = (clone $todayTransactions)->where('status', Transaction::STATUS_FAILED)->count();
            $gmvCents = (int) (clone $todayTransactions)
                ->where('status', Transaction::STATUS_SUCCESS)
                ->sum('sell_price_cents');
            $profitCents = (int) (clone $todayTransactions)
                ->where('status', Transaction::STATUS_SUCCESS)
                ->sum(DB::raw('sell_price_cents - amount_cents'));
        }

        $gmvRupiah = (int) ($gmvCents / 100);

        $profitRupiah = null;
        if ($role === AdminRole::SUPER_ADMIN || $role === AdminRole::FINANCE_ADMIN) {
            $profitRupiah = (int) ($profitCents / 100);
        }

        $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0.0;

        // Pending queues (active transactions & topups)
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
    }

    /**
     * Dapatkan tren transaksi per jam selama 24 jam terakhir dari daily_transaction_metrics.
     * Hanya membaca maksimal 24 baris data terindeks!
     *
     * @return array{labels: array<int, string>, success: array<int, int>, failed: array<int, int>}
     */
    public function getHourlyTrend24Hours(?Carbon $now = null): array
    {
        $currentTime = $now ?? Carbon::now();
        $labels = [];
        $hours = [];

        for ($i = 23; $i >= 0; $i--) {
            $hourTime = (clone $currentTime)->subHours($i);
            $datePart = $hourTime->format('Y-m-d');
            $hourPart = (int) $hourTime->format('H');
            $dictKey = "{$datePart}_{$hourPart}";

            $labels[] = $hourTime->format('H:00');
            $hours[$dictKey] = [
                'success' => 0,
                'failed' => 0,
            ];
        }

        $earliest = (clone $currentTime)->subHours(23)->startOfHour();
        $earliestDate = $earliest->format('Y-m-d');

        // Ambil data metrik dari daily_transaction_metrics (maksimal 24-25 row)
        $metrics = DailyTransactionMetric::where('metric_date', '>=', $earliestDate)->get();

        // Fallback untuk backward compatibility jika belum ada baris metrik tetapi transaksi ada
        if ($metrics->isEmpty() && Transaction::where('created_at', '>=', $earliest)->exists()) {
            $rawTransactions = Transaction::where('created_at', '>=', $earliest)
                ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED])
                ->get(['status', 'created_at']);

            foreach ($rawTransactions as $tx) {
                $key = $tx->created_at->format('Y-m-d') . '_' . (int) $tx->created_at->format('H');
                if (isset($hours[$key])) {
                    if ($tx->status === Transaction::STATUS_SUCCESS) {
                        $hours[$key]['success']++;
                    } elseif ($tx->status === Transaction::STATUS_FAILED) {
                        $hours[$key]['failed']++;
                    }
                }
            }
        } else {
            foreach ($metrics as $metric) {
                $mDate = is_string($metric->metric_date) ? substr($metric->metric_date, 0, 10) : $metric->metric_date->format('Y-m-d');
                $key = "{$mDate}_{$metric->hour}";
                if (isset($hours[$key])) {
                    $hours[$key]['success'] = (int) $metric->success_count;
                    $hours[$key]['failed'] = (int) $metric->failed_count;
                }
            }
        }

        $successData = [];
        $failedData = [];
        foreach ($hours as $item) {
            $successData[] = $item['success'];
            $failedData[] = $item['failed'];
        }

        return [
            'labels' => $labels,
            'success' => $successData,
            'failed' => $failedData,
        ];
    }

    /**
     * Upsert & atomic increment ke tabel daily_transaction_metrics.
     */
    protected function upsertMetric(
        string $date,
        int $hour,
        int $totalDelta,
        int $successDelta,
        int $failedDelta,
        int $pendingDelta,
        int $gmvCentsDelta,
        int $costCentsDelta,
        int $profitCentsDelta
    ): void {
        $formattedDate = \Carbon\Carbon::parse($date)->format('Y-m-d');

        $metric = DailyTransactionMetric::where('metric_date', $formattedDate)
            ->where('hour', $hour)
            ->first();

        if (! $metric) {
            try {
                $metric = DailyTransactionMetric::create([
                    'metric_date' => $formattedDate,
                    'hour' => $hour,
                    'total_count' => 0,
                    'success_count' => 0,
                    'failed_count' => 0,
                    'pending_count' => 0,
                    'gmv_cents' => 0,
                    'cost_cents' => 0,
                    'profit_cents' => 0,
                ]);
            } catch (\Throwable) {
                $metric = DailyTransactionMetric::where('metric_date', $formattedDate)
                    ->where('hour', $hour)
                    ->first();
            }
        }

        if (! $metric) {
            return;
        }

        $updates = [];
        if ($totalDelta !== 0) {
            $updates['total_count'] = DB::raw("total_count + {$totalDelta}");
        }
        if ($successDelta !== 0) {
            $updates['success_count'] = DB::raw("success_count + {$successDelta}");
        }
        if ($failedDelta !== 0) {
            $updates['failed_count'] = DB::raw("failed_count + {$failedDelta}");
        }
        if ($pendingDelta !== 0) {
            // Lindungi agar pending_count tidak minus
            $updates['pending_count'] = DB::raw("CASE WHEN pending_count + {$pendingDelta} < 0 THEN 0 ELSE pending_count + {$pendingDelta} END");
        }
        if ($gmvCentsDelta !== 0) {
            $updates['gmv_cents'] = DB::raw("gmv_cents + {$gmvCentsDelta}");
        }
        if ($costCentsDelta !== 0) {
            $updates['cost_cents'] = DB::raw("cost_cents + {$costCentsDelta}");
        }
        if ($profitCentsDelta !== 0) {
            $updates['profit_cents'] = DB::raw("profit_cents + {$profitCentsDelta}");
        }

        if (! empty($updates)) {
            $updates['updated_at'] = now();
            DailyTransactionMetric::where('id', $metric->id)->update($updates);
        }
    }

    /**
     * Update atomic counter di Redis / Cache in-memory.
     */
    protected function incrementAtomicCounters(
        string $date,
        int $hour,
        int $totalDelta,
        int $successDelta,
        int $failedDelta,
        int $gmvCentsDelta,
        int $profitCentsDelta
    ): void {
        try {
            if ($totalDelta > 0) {
                $this->bumpCache("metrics:trx:{$date}:total", $totalDelta);
            }
            if ($successDelta > 0) {
                $this->bumpCache("metrics:trx:{$date}:success", $successDelta);
                $this->bumpCache("metrics:trx:{$date}:{$hour}:success", $successDelta);
            }
            if ($failedDelta > 0) {
                $this->bumpCache("metrics:trx:{$date}:failed", $failedDelta);
                $this->bumpCache("metrics:trx:{$date}:{$hour}:failed", $failedDelta);
            }
            if ($gmvCentsDelta > 0) {
                $this->bumpCache("metrics:trx:{$date}:gmv_cents", $gmvCentsDelta);
            }
            if ($profitCentsDelta !== 0) {
                $this->bumpCache("metrics:trx:{$date}:profit_cents", $profitCentsDelta);
            }
        } catch (\Throwable) {
            // Gracefully ignore jika in-memory cache/redis mengalami gangguan
        }
    }

    /**
     * Atomic increment dengan fallback antara Redis dan Cache store.
     */
    protected function bumpCache(string $key, int $amount): void
    {
        try {
            Redis::incrby($key, $amount);
            Redis::expire($key, 86400 * 7);
        } catch (\Throwable) {
            if (! Cache::has($key)) {
                Cache::put($key, $amount, now()->addDays(7));
            } else {
                Cache::increment($key, $amount);
            }
        }
    }

    /**
     * Ekstraksi nilai cents dari Transaction (baik berupa Money value object maupun integer raw).
     */
    protected function extractCents(Transaction $transaction, string $field): int
    {
        $val = $transaction->{$field};
        if ($val instanceof Money) {
            return $val->toCents();
        }

        $rawAttributes = $transaction->getAttributes();
        return (int) ($rawAttributes[$field] ?? 0);
    }
}
