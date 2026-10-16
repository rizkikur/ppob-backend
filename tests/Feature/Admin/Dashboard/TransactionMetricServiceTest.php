<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Services\AdminDashboardService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\DailyTransactionMetric;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionMetricService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionMetricServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private TransactionMetricService $metricService;
    private AdminDashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->metricService = app(TransactionMetricService::class);
        $this->dashboardService = app(AdminDashboardService::class);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular tier']
        );

        $this->user = User::create([
            'name' => 'Test User',
            'phone' => '081299998888',
            'user_tier_id' => $tier->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'PULSA',
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10K',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_transaction_creation_increments_daily_metric_table_atomically(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $hour = (int) Carbon::now()->hour;

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081299998888',
            'amount_cents' => 1000000, // 10.000
            'sell_price_cents' => 1200000, // 12.000
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'metric-test-1',
        ]);

        $metric = DailyTransactionMetric::where('metric_date', $today)
            ->where('hour', $hour)
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(1, $metric->total_count);
        $this->assertEquals(0, $metric->success_count);
        $this->assertEquals(0, $metric->failed_count);
        $this->assertEquals(1, $metric->pending_count);
    }

    public function test_transaction_status_success_updates_success_count_gmv_and_profit(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $hour = (int) Carbon::now()->hour;

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081299998888',
            'amount_cents' => 1000000, // modal 10.000
            'sell_price_cents' => 1250000, // jual 12.500 -> profit 2.500
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'metric-test-2',
        ]);

        // Transisi ke status sukses
        $tx->update(['status' => Transaction::STATUS_SUCCESS]);

        $metric = DailyTransactionMetric::where('metric_date', $today)
            ->where('hour', $hour)
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(1, $metric->total_count);
        $this->assertEquals(1, $metric->success_count);
        $this->assertEquals(0, $metric->failed_count);
        $this->assertEquals(0, $metric->pending_count);
        $this->assertEquals(1250000, $metric->gmv_cents);
        $this->assertEquals(250000, $metric->profit_cents);
        $this->assertEquals(12500, $metric->gmv_rupiah);
        $this->assertEquals(2500, $metric->profit_rupiah);
    }

    public function test_transaction_status_failed_updates_failed_count(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $hour = (int) Carbon::now()->hour;

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081299998888',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1250000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'metric-test-3',
        ]);

        $tx->update(['status' => Transaction::STATUS_FAILED]);

        $metric = DailyTransactionMetric::where('metric_date', $today)
            ->where('hour', $hour)
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(1, $metric->total_count);
        $this->assertEquals(0, $metric->success_count);
        $this->assertEquals(1, $metric->failed_count);
        $this->assertEquals(0, $metric->pending_count);
        $this->assertEquals(0, $metric->gmv_cents);
    }

    public function test_hourly_trend_reads_from_daily_transaction_metrics_without_raw_table_scans(): void
    {
        $now = Carbon::now();
        $dateStr = $now->format('Y-m-d');
        $hour = (int) $now->hour;

        // Buat data langsung di tabel daily_transaction_metrics
        DailyTransactionMetric::create([
            'metric_date' => $dateStr,
            'hour' => $hour,
            'total_count' => 15,
            'success_count' => 12,
            'failed_count' => 3,
            'pending_count' => 0,
            'gmv_cents' => 15000000,
            'cost_cents' => 12000000,
            'profit_cents' => 3000000,
        ]);

        // Verifikasi getHourlyTrend24Hours mengambil data dari summary table
        $trend = $this->metricService->getHourlyTrend24Hours($now);

        $this->assertCount(24, $trend['labels']);
        $this->assertCount(24, $trend['success']);
        $this->assertCount(24, $trend['failed']);

        // Jam terakhir (index 23) harus sesuai dengan data metric kita
        $this->assertEquals(12, $trend['success'][23]);
        $this->assertEquals(3, $trend['failed'][23]);
    }

    public function test_summary_metrics_aggregates_from_daily_transaction_metrics(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        // Jam 08:00
        DailyTransactionMetric::create([
            'metric_date' => $today,
            'hour' => 8,
            'total_count' => 10,
            'success_count' => 8,
            'failed_count' => 2,
            'gmv_cents' => 8000000, // Rp 80.000
            'cost_cents' => 6000000,
            'profit_cents' => 2000000, // Rp 20.000
        ]);

        // Jam 09:00
        DailyTransactionMetric::create([
            'metric_date' => $today,
            'hour' => 9,
            'total_count' => 20,
            'success_count' => 18,
            'failed_count' => 2,
            'gmv_cents' => 20000000, // Rp 200.000
            'cost_cents' => 15000000,
            'profit_cents' => 5000000, // Rp 50.000
        ]);

        $summary = $this->metricService->getSummaryMetrics(AdminRole::SUPER_ADMIN);

        $this->assertEquals(30, $summary['transactions_count']);
        $this->assertEquals(26, $summary['success_count']);
        $this->assertEquals(4, $summary['failed_count']);
        $this->assertEquals(280000, $summary['gmv_today']); // Rp 280.000
        $this->assertEquals(70000, $summary['profit_today']); // Rp 70.000
        $this->assertEquals(86.67, $summary['success_rate']);
    }
}
