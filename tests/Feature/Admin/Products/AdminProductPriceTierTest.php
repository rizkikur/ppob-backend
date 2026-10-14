<?php

namespace Tests\Feature\Admin\Products;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\ProductTierPrice;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProductPriceTierTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Product $product;
    private UserTier $tierRegular;
    private UserTier $tierSilver;
    private UserTier $tierGold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Finance Controller',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $this->tierRegular = UserTier::firstOrCreate(['name' => 'end_user'], ['label' => 'Regular']);
        $this->tierSilver = UserTier::firstOrCreate(['name' => 'agent'], ['label' => 'Silver / Agen']);
        $this->tierGold = UserTier::firstOrCreate(['name' => 'reseller'], ['label' => 'Gold / Reseller']);

        $category = ProductCategory::create([
            'name' => 'Pulsa Reguler',
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
            'sku_code' => 'TSEL50K',
            'name' => 'Telkomsel 50.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 5000000, // Rp 50.000 modal
            'is_active' => true,
        ]);
    }

    public function test_validation_prevents_selling_price_below_provider_base_cost(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Trying to set regular price = Rp 49.000 while base cost is Rp 50.000
        $response = $this->put("/admin/products/{$this->product->id}/prices", [
            'tier_prices' => [
                $this->tierRegular->id => 49000, // Negative margin!
                $this->tierSilver->id => 50500,
            ],
        ]);

        $response->assertSessionHasErrors(["tier_prices.{$this->tierRegular->id}"]);
        $this->assertDatabaseMissing('product_tier_prices', [
            'product_id' => $this->product->id,
            'user_tier_id' => $this->tierRegular->id,
        ]);
    }

    public function test_updating_tier_prices_persists_and_logs_audit_trail(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->put("/admin/products/{$this->product->id}/prices", [
            'tier_prices' => [
                $this->tierRegular->id => 51000, // Rp 51.000
                $this->tierSilver->id => 50500,  // Rp 50.500
                $this->tierGold->id => 50200,    // Rp 50.200
            ],
        ]);

        $response->assertRedirect("/admin/products/{$this->product->id}/edit");
        $response->assertSessionHas('success');

        // Verify prices in DB
        $this->assertDatabaseHas('product_tier_prices', [
            'product_id' => $this->product->id,
            'user_tier_id' => $this->tierRegular->id,
            'sell_price_cents' => 5100000,
        ]);

        $this->assertDatabaseHas('product_tier_prices', [
            'product_id' => $this->product->id,
            'user_tier_id' => $this->tierSilver->id,
            'sell_price_cents' => 5050000,
        ]);

        $this->assertDatabaseHas('product_tier_prices', [
            'product_id' => $this->product->id,
            'user_tier_id' => $this->tierGold->id,
            'sell_price_cents' => 5020000,
        ]);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'product.tier_prices_update',
            'target_type' => 'Product',
            'target_id' => (string) $this->product->id,
        ]);
    }
}
