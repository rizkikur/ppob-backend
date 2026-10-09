<?php

namespace Tests\Feature\Mobile;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperatorPrefixTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Provider $telkomsel;

    private Provider $indosat;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = UserTier::create([
            'name' => 'end_user',
            'label' => 'Konsumen',
        ]);

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Budi Operator Test',
            'phone' => '081211112222',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->telkomsel = Provider::create([
            'name' => 'Telkomsel',
            'code' => 'telkomsel',
            'driver' => 'TelkomselDriver',
            'is_active' => true,
        ]);

        $this->indosat = Provider::create([
            'name' => 'Indosat Ooredoo',
            'code' => 'indosat',
            'driver' => 'IndosatDriver',
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $category->id,
            'sku_code' => 'TSEL5K',
            'name' => 'Telkomsel 5.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 510000,
            'admin_fee_cents' => 40000,
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $category->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1010000,
            'admin_fee_cents' => 65000,
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $this->indosat->id,
            'category_id' => $category->id,
            'sku_code' => 'ISAT10K',
            'name' => 'Indosat 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1015000,
            'admin_fee_cents' => 60000,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_operator_prefix(): void
    {
        $response = $this->getJson('/products/operator-prefix?phone=081234567890');
        $response->assertStatus(401);
    }

    public function test_detect_telkomsel_operator_with_various_formats(): void
    {
        Sanctum::actingAs($this->user);

        // Format 1: 0812
        $response1 = $this->getJson('/products/operator-prefix?phone=081234567890');
        $response1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prefix', '0812')
            ->assertJsonPath('data.provider.code', 'telkomsel')
            ->assertJsonCount(2, 'data.products');

        // Format 2: +62813
        $response2 = $this->getJson('/api/v1/products/operator-prefix?phone=+6281399887766');
        $response2->assertStatus(200)
            ->assertJsonPath('data.prefix', '0813')
            ->assertJsonPath('data.provider.code', 'telkomsel');

        // Format 3: 62852 with dashes
        $response3 = $this->getJson('/products/operator-prefix?phone=62852-1122-3344');
        $response3->assertStatus(200)
            ->assertJsonPath('data.prefix', '0852')
            ->assertJsonPath('data.provider.code', 'telkomsel');
    }

    public function test_detect_indosat_operator(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/products/operator-prefix?phone=085612345678');
        $response->assertStatus(200)
            ->assertJsonPath('data.prefix', '0856')
            ->assertJsonPath('data.provider.code', 'indosat')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.sku_code', 'ISAT10K');
    }

    public function test_unknown_operator_prefix_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/products/operator-prefix?phone=0800123456');
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'OPERATOR_NOT_FOUND');
    }

    public function test_inactive_provider_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->telkomsel->update(['is_active' => false]);

        $response = $this->getJson('/products/operator-prefix?phone=081234567890');
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'PROVIDER_INACTIVE');
    }
}
