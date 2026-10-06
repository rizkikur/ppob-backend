<?php

namespace Tests\Feature\Product;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\ProductTierPrice;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Services\ProductPricingService;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TierPricingTest extends TestCase
{
    use RefreshDatabase;

    private UserTier $endUserTier;

    private UserTier $agentTier;

    private User $endUser;

    private User $agentUser;

    private Product $product;

    private ProductPricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endUserTier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->agentTier = UserTier::firstOrCreate(
            ['name' => 'agent'],
            ['label' => 'Agen', 'description' => 'Mitra agen']
        );

        $this->endUser = User::create([
            'user_tier_id' => $this->endUserTier->id,
            'name' => 'End User Customer',
            'phone' => '081233330001',
            'email' => 'enduser@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->agentUser = User::create([
            'user_tier_id' => $this->agentTier->id,
            'name' => 'Agent Customer',
            'phone' => '081233330002',
            'email' => 'agent@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'Telkomsel',
            'code' => 'telkomsel',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'TLS-5000',
            'name' => 'Pulsa Telkomsel 5.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 500000, // Rp 5.000,00
            'admin_fee_cents' => 50000,  // Rp 500,00
            'is_active' => true,
        ]);

        $this->pricingService = app(ProductPricingService::class);
    }

    /**
     * User dengan tier berbeda menerima harga jual yang berbeda sesuai tabel product_tier_prices.
     */
    public function test_different_user_tiers_receive_different_sell_prices(): void
    {
        // Tier end_user: Rp 6.000,00 (600.000 cents)
        ProductTierPrice::create([
            'product_id' => $this->product->id,
            'user_tier_id' => $this->endUserTier->id,
            'sell_price_cents' => 600000,
        ]);

        // Tier agent: Rp 5.500,00 (550.000 cents)
        ProductTierPrice::create([
            'product_id' => $this->product->id,
            'user_tier_id' => $this->agentTier->id,
            'sell_price_cents' => 550000,
        ]);

        // 1. Cek dari sisi End User
        Sanctum::actingAs($this->endUser);
        $responseEndUser = $this->getJson('/products');

        $responseEndUser->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'sku_code' => 'TLS-5000',
                        'sell_price' => 600000,
                        'sell_price_display' => 'Rp 6.000,00',
                    ],
                ],
            ]);

        // 2. Cek dari sisi Agent
        Sanctum::actingAs($this->agentUser);
        $responseAgent = $this->getJson('/products');

        $responseAgent->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'sku_code' => 'TLS-5000',
                        'sell_price' => 550000,
                        'sell_price_display' => 'Rp 5.500,00',
                    ],
                ],
            ]);
    }

    /**
     * Jika tidak ada product_tier_price untuk tier user, harga otomatis fallback ke base_price + admin_fee.
     */
    public function test_product_without_tier_price_falls_back_to_base_plus_admin_fee(): void
    {
        // base_price_cents (500000) + admin_fee_cents (50000) = 550000 cents (Rp 5.500,00)
        Sanctum::actingAs($this->endUser);

        $response = $this->getJson('/products');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'sku_code' => 'TLS-5000',
                        'sell_price' => 550000,
                        'sell_price_display' => 'Rp 5.500,00',
                    ],
                ],
            ]);
    }

    /**
     * Pengujian langsung pada method ProductPricingService::getPriceForUser().
     */
    public function test_pricing_service_direct_unit_computation(): void
    {
        // Fallback default
        $fallbackPrice = $this->pricingService->getPriceForUser($this->product, $this->endUser);
        $this->assertInstanceOf(Money::class, $fallbackPrice);
        $this->assertEquals(550000, $fallbackPrice->toCents());

        // Custom tier price
        ProductTierPrice::create([
            'product_id' => $this->product->id,
            'user_tier_id' => $this->endUserTier->id,
            'sell_price_cents' => 625000, // Rp 6.250,00
        ]);

        $customPrice = $this->pricingService->getPriceForUser($this->product, $this->endUser);
        $this->assertEquals(625000, $customPrice->toCents());
        $this->assertEquals('Rp 6.250,00', $customPrice->format());
    }
}
