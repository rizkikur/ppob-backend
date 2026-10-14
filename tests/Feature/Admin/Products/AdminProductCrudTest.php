<?php

namespace Tests\Feature\Admin\Products;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Provider $provider;
    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Ops Supervisor',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Pulsa Reguler',
            'code' => 'PULSA',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_products(): void
    {
        $response = $this->get('/admin/products');

        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_product_list_with_filters(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1010000,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee('TSEL10K');
        $response->assertSee('Telkomsel 10.000');
        $response->assertSee('Digiflazz');

        // Test Filter
        $responseFilter = $this->get('/admin/products?q=TSEL10K');
        $responseFilter->assertStatus(200);
        $responseFilter->assertSee('TSEL10K');
    }

    public function test_admin_can_store_new_product_and_it_creates_supplier_route_and_audit(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/products', [
            'sku_code' => 'ISAT25K',
            'name' => 'Indosat Ooredoo 25.000',
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'product_type' => 'prepaid',
            'base_price_rupiah' => 24800,
            'admin_fee_rupiah' => 0,
            'is_active' => 1,
        ]);

        $product = Product::where('sku_code', 'ISAT25K')->first();
        $this->assertNotNull($product);
        $this->assertEquals(2480000, $product->base_price_cents->toCents());

        $response->assertRedirect("/admin/products/{$product->id}/edit");
        $response->assertSessionHas('success');

        // Check ProductSupplierRoute automatically created
        $this->assertDatabaseHas('product_supplier_routes', [
            'product_id' => $product->id,
            'provider_id' => $this->provider->id,
            'priority' => 1,
        ]);

        // Check Audit Log
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'product.create',
            'target_type' => 'Product',
            'target_id' => (string) $product->id,
        ]);
    }

    public function test_admin_can_update_product_metadata(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'XL10K',
            'name' => 'XL Axiata 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1020000,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->put("/admin/products/{$product->id}", [
            'sku_code' => 'XL10K_UPDATED',
            'name' => 'XL Axiata 10.000 Promo',
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'product_type' => 'prepaid',
            'base_price_rupiah' => 10050,
            'is_active' => 1,
        ]);

        $response->assertRedirect("/admin/products/{$product->id}/edit");
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals('XL10K_UPDATED', $product->sku_code);
        $this->assertEquals('XL Axiata 10.000 Promo', $product->name);
        $this->assertEquals(1005000, $product->base_price_cents->toCents());

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'product.update',
            'target_type' => 'Product',
            'target_id' => (string) $product->id,
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'TRI5K',
            'name' => 'Tri 5.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 500000,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->delete("/admin/products/{$product->id}");

        $response->assertRedirect('/admin/products');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'product.delete',
        ]);
    }

    public function test_category_management_and_deletion_restriction(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Create new category
        $responseStore = $this->post('/admin/categories', [
            'code' => 'GAME',
            'name' => 'Voucher Game',
            'sort_order' => 5,
        ]);
        $responseStore->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('product_categories', ['code' => 'GAME']);

        $category = ProductCategory::where('code', 'GAME')->first();

        // Delete empty category succeeds
        $responseDel = $this->delete("/admin/categories/{$category->id}");
        $responseDel->assertRedirect('/admin/categories');
        $this->assertDatabaseMissing('product_categories', ['code' => 'GAME']);

        // Delete category with existing products fails
        Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'PULSA_TEST',
            'name' => 'Pulsa Test',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
        ]);

        $responseDelBlocked = $this->delete("/admin/categories/{$this->category->id}");
        $responseDelBlocked->assertRedirect('/admin/categories');
        $responseDelBlocked->assertSessionHas('error');
        $this->assertDatabaseHas('product_categories', ['id' => $this->category->id]);
    }

    public function test_admin_can_toggle_product_status_via_ajax(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'TOGGLE_TEST',
            'name' => 'Toggle Test',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->postJson("/admin/products/{$product->id}/toggle");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);

        // Toggle back to true
        $response2 = $this->postJson("/admin/products/{$product->id}/toggle");
        $response2->assertStatus(200);
        $response2->assertJson([
            'success' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_emergency_bulk_freeze_and_unfreeze_products(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'FREEZE_1',
            'name' => 'Freeze 1',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'FREEZE_2',
            'name' => 'Freeze 2',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 2000000,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        // Freeze all in category
        $response = $this->post('/admin/products/bulk/status', [
            'category_id' => $this->category->id,
            'is_active' => '0',
            'reason' => 'Insiden gangguan massal jalur Telkomsel',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['sku_code' => 'FREEZE_1', 'is_active' => false]);
        $this->assertDatabaseHas('products', ['sku_code' => 'FREEZE_2', 'is_active' => false]);

        // Unfreeze
        $responseUnfreeze = $this->post('/admin/products/bulk/status', [
            'category_id' => $this->category->id,
            'is_active' => '1',
            'reason' => 'Jalur Telkomsel sudah normal kembali',
        ]);

        $responseUnfreeze->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['sku_code' => 'FREEZE_1', 'is_active' => true]);
        $this->assertDatabaseHas('products', ['sku_code' => 'FREEZE_2', 'is_active' => true]);
    }

    public function test_admin_can_sync_provider_pricelist(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/products/sync-provider', [
            'provider_id' => $this->provider->id,
        ]);

        $response->assertSessionHas('success');
        // New SKUs are auto-imported
        $this->assertDatabaseHas('products', [
            'provider_id' => $this->provider->id,
            'sku_code' => 'DIGIFLAZZ-PULSA5K',
        ]);
    }

    public function test_admin_can_update_multi_provider_supplier_routes(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'MULTI_ROUTE_SKU',
            'name' => 'Multi Route SKU',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);

        $secondProvider = Provider::create([
            'name' => 'Tokovoucher',
            'code' => 'TOKOVOUCHER',
            'driver' => 'TokovoucherDriver',
            'priority' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->post("/admin/products/{$product->id}/routes", [
            'routes' => [
                [
                    'provider_id' => $this->provider->id,
                    'priority' => 1,
                    'is_active' => '1',
                ],
                [
                    'provider_id' => $secondProvider->id,
                    'priority' => 2,
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('product_supplier_routes', [
            'product_id' => $product->id,
            'provider_id' => $secondProvider->id,
            'priority' => 2,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_bulk_adjust_prices(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'BULK_PRICE_SKU',
            'name' => 'Bulk Price SKU',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 500000, // Rp 5.000 modal
            'is_active' => true,
        ]);

        $tier = \App\Domain\Auth\Models\UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular']
        );

        \App\Domain\Product\Models\ProductTierPrice::create([
            'product_id' => $product->id,
            'user_tier_id' => $tier->id,
            'sell_price_cents' => 520000, // Rp 5.200 jual
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/products/bulk/prices', [
            'category_id' => $this->category->id,
            'adjustment_type' => 'fixed',
            'direction' => 'increase',
            'amount' => 100, // +Rp 100
            'reason' => 'Penyesuaian inflasi margin',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('product_tier_prices', [
            'product_id' => $product->id,
            'user_tier_id' => $tier->id,
            'sell_price_cents' => 530000, // Rp 5.300
        ]);
    }
}

