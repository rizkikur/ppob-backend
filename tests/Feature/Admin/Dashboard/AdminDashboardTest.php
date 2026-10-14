<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\TopupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $opsAdmin;
    private Admin $financeAdmin;
    private User $user;
    private Product $product;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->opsAdmin = Admin::create([
            'name' => 'Ops Admin',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->financeAdmin = Admin::create([
            'name' => 'Finance Admin',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::FINANCE_ADMIN,
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

        $this->provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_dashboard_with_all_components(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        $response = $this->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Operasional Realtime');
        $response->assertSee('GMV Hari Ini');
        $response->assertSee('Volume Transaksi');
        $response->assertSee('Status Provider dan Circuit Breaker');
        $response->assertSee('Digiflazz');
    }

    public function test_ops_admin_cannot_see_gross_profit_metric(): void
    {
        // Create sample success transaction: sell 12.000 (1.200.000 cents), cost 10.000 (1.000.000 cents)
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'trx-1',
        ]);

        $this->actingAs($this->opsAdmin, 'admin');

        $response = $this->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Rp ***');
        $response->assertSee('Disamarkan (Akses Terbatas Peran)');
    }

    public function test_super_admin_and_finance_can_see_gross_profit(): void
    {
        // 200.000 cents profit = Rp 2.000
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'trx-profit-1',
        ]);

        // Super Admin view
        $this->actingAs($this->superAdmin, 'admin');
        $superResponse = $this->get('/admin/dashboard');
        $superResponse->assertStatus(200);
        $superResponse->assertSee('Rp 2.000');

        Cache::flush();

        // Finance Admin view
        $this->actingAs($this->financeAdmin, 'admin');
        $financeResponse = $this->get('/admin/dashboard');
        $financeResponse->assertStatus(200);
        $financeResponse->assertSee('Rp 2.000');
    }

    public function test_metrics_endpoint_returns_valid_json_structure(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        $response = $this->getJson('/admin/dashboard/metrics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'timestamp',
            'metrics' => [
                'gmv_today',
                'profit_today',
                'transactions_count',
                'success_count',
                'failed_count',
                'success_rate',
                'pending_transactions',
                'pending_topups',
            ],
            'providers' => [
                '*' => [
                    'id',
                    'name',
                    'code',
                    'circuit_breaker_state',
                    'status',
                ],
            ],
        ]);
    }

    public function test_pending_transaction_counter_matches_actual_database_records(): void
    {
        // 2 pending transactions
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'trx-pend-1',
        ]);
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1200000,
            'status' => Transaction::STATUS_PROCESSING,
            'idempotency_key' => 'trx-proc-2',
        ]);

        // 1 pending top-up
        TopupRequest::create([
            'user_id' => $this->user->id,
            'amount_cents' => 5000000,
            'method' => 'manual_transfer',
            'status' => TopupRequest::STATUS_PENDING,
            'idempotency_key' => 'topup-1',
        ]);

        $this->actingAs($this->superAdmin, 'admin');

        $response = $this->getJson('/admin/dashboard/metrics');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('metrics.pending_transactions'));
        $this->assertEquals(1, $response->json('metrics.pending_topups'));
    }

    public function test_circuit_breaker_alert_badge_displayed_when_provider_is_tripped(): void
    {
        $circuitBreakerService = app(CircuitBreakerService::class);
        $circuitBreakerService->trip($this->provider->code);

        $this->actingAs($this->superAdmin, 'admin');

        $response = $this->getJson('/admin/dashboard/metrics');

        $response->assertStatus(200);
        $providerData = collect($response->json('providers'))->firstWhere('code', $this->provider->code);

        $this->assertNotNull($providerData);
        $this->assertEquals('TRIPPED', $providerData['status']);
        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $providerData['circuit_breaker_state']);
    }
}
