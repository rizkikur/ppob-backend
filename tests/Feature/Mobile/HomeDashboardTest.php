<?php

namespace Tests\Feature\Mobile;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductCategory $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = UserTier::create([
            'name' => 'reseller',
            'label' => 'Reseller Grosir',
            'description' => 'Tier mitra reseller',
        ]);

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Ahmad Mobile',
            'phone' => '081299998888',
            'email' => 'ahmad@example.com',
            'is_active' => true,
            'is_verified' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 75000000, // Rp 750.000
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Pulsa Reguler',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'Telkomsel',
            'code' => 'telkomsel',
            'driver' => 'TelkomselDriver',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $this->category->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_home_dashboard(): void
    {
        $response = $this->getJson('/home');
        $response->assertStatus(401);

        $responseV1 = $this->getJson('/api/v1/home');
        $responseV1->assertStatus(401);
    }

    public function test_authenticated_user_can_get_home_dashboard(): void
    {
        Sanctum::actingAs($this->user);

        // Buat 2 transaksi
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081299998888',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1050000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idemp_home_1',
        ]);

        $response = $this->getJson('/home');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Ahmad Mobile')
            ->assertJsonPath('data.user.phone', '081299998888')
            ->assertJsonPath('data.user.tier.label', 'Reseller Grosir')
            ->assertJsonPath('data.wallet.balance_cents', 75000000)
            ->assertJsonPath('data.categories.0.code', 'pulsa')
            ->assertJsonPath('data.recent_transactions.0.sku_code', 'TSEL10K')
            ->assertJsonPath('data.recent_transactions.0.status', 'success');
    }

    public function test_home_dashboard_limits_recent_transactions_to_five(): void
    {
        Sanctum::actingAs($this->user);

        for ($i = 1; $i <= 7; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'product_id' => $this->product->id,
                'customer_number' => '081299998888',
                'amount_cents' => 1000000,
                'sell_price_cents' => 1050000,
                'status' => Transaction::STATUS_SUCCESS,
                'idempotency_key' => "idemp_home_loop_{$i}",
                'created_at' => now()->addMinutes($i),
            ]);
        }

        $response = $this->getJson('/api/v1/home');

        $response->assertStatus(200);
        $recent = $response->json('data.recent_transactions');
        $this->assertCount(5, $recent);
    }
}
