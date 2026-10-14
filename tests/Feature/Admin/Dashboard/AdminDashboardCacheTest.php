<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminDashboardService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private User $user;
    private Product $product;
    private AdminDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->service = app(AdminDashboardService::class);

        $this->superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular tier']
        );

        $this->user = User::create([
            'name' => 'Customer Test',
            'phone' => '081234567890',
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
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_metrics_data_is_served_from_cache_on_subsequent_calls(): void
    {
        // 1 initial transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'trx-c-1',
        ]);

        // First call populates cache
        $initialMetrics = $this->service->getSummaryMetrics(AdminRole::SUPER_ADMIN);
        $this->assertEquals(1, $initialMetrics['transactions_count']);
        $this->assertEquals(12000, $initialMetrics['gmv_today']);

        // Insert second transaction directly in database
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'trx-c-2',
        ]);

        // Second call should return cached count (1 instead of 2)
        $cachedMetrics = $this->service->getSummaryMetrics(AdminRole::SUPER_ADMIN);
        $this->assertEquals(1, $cachedMetrics['transactions_count']);
    }

    public function test_clear_dashboard_cache_invalidates_cached_metrics(): void
    {
        // 1 initial transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'trx-c-3',
        ]);

        $this->service->getSummaryMetrics(AdminRole::SUPER_ADMIN);

        // Insert second transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'trx-c-4',
        ]);

        // Clear cache
        $this->service->clearDashboardCache();

        // Fresh call returns updated count
        $freshMetrics = $this->service->getSummaryMetrics(AdminRole::SUPER_ADMIN);
        $this->assertEquals(2, $freshMetrics['transactions_count']);
        $this->assertEquals(24000, $freshMetrics['gmv_today']);
    }
}
