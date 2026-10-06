<?php

namespace Tests\Feature\Product;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    private ProductCategory $pulsaCategory;

    private ProductCategory $listrikCategory;

    private Provider $telkomsel;

    private Provider $pln;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Catalog User',
            'phone' => '081234560001',
            'email' => 'catalog@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->pulsaCategory = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->listrikCategory = ProductCategory::create([
            'name' => 'Token Listrik',
            'code' => 'token_listrik',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->telkomsel = Provider::create([
            'name' => 'Telkomsel',
            'code' => 'telkomsel',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
        ]);

        $this->pln = Provider::create([
            'name' => 'PLN',
            'code' => 'pln',
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_pln',
            'is_active' => true,
        ]);
    }

    /**
     * Endpoint categories mengembalikan kategori aktif dan terurut sort_order.
     */
    public function test_categories_list_returns_active_categories_sorted(): void
    {
        // Buat kategori tidak aktif
        ProductCategory::create([
            'name' => 'Kategori Nonaktif',
            'code' => 'nonaktif',
            'sort_order' => 0,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/products/categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('pulsa', $data[0]['code']);
        $this->assertEquals('token_listrik', $data[1]['code']);
    }

    /**
     * Endpoint products mengembalikan produk yang berstatus aktif.
     */
    public function test_products_list_returns_active_products(): void
    {
        $activeProduct = Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $this->pulsaCategory->id,
            'sku_code' => 'TLS-5000',
            'name' => 'Telkomsel 5.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 500000,
            'admin_fee_cents' => 50000,
            'is_active' => true,
        ]);

        // Produk non-aktif
        Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $this->pulsaCategory->id,
            'sku_code' => 'TLS-INACTIVE',
            'name' => 'Telkomsel Inactive',
            'product_type' => 'prepaid',
            'base_price_cents' => 500000,
            'admin_fee_cents' => 50000,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/products');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('TLS-5000', $data[0]['sku_code']);
        $this->assertEquals('Telkomsel', $data[0]['provider']);
        $this->assertEquals('Pulsa', $data[0]['category']['name']);
    }

    /**
     * Filter produk berdasarkan category code.
     */
    public function test_products_list_filter_by_category(): void
    {
        Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $this->pulsaCategory->id,
            'sku_code' => 'TLS-10000',
            'name' => 'Telkomsel 10.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 1000000,
            'admin_fee_cents' => 50000,
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $this->pln->id,
            'category_id' => $this->listrikCategory->id,
            'sku_code' => 'PLN-20000',
            'name' => 'Token PLN 20.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 2000000,
            'admin_fee_cents' => 250000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/products?category=pulsa');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('TLS-10000', $data[0]['sku_code']);

        $responseListrik = $this->getJson('/products?category=token_listrik');
        $responseListrik->assertStatus(200);
        $this->assertCount(1, $responseListrik->json('data'));
        $this->assertEquals('PLN-20000', $responseListrik->json('data.0.sku_code'));
    }

    /**
     * Filter produk berdasarkan provider code.
     */
    public function test_products_list_filter_by_provider(): void
    {
        Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $this->pulsaCategory->id,
            'sku_code' => 'TLS-5000',
            'name' => 'Telkomsel 5.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 500000,
            'admin_fee_cents' => 50000,
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $this->pln->id,
            'category_id' => $this->listrikCategory->id,
            'sku_code' => 'PLN-20000',
            'name' => 'Token PLN 20.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 2000000,
            'admin_fee_cents' => 250000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/products?provider=telkomsel');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('TLS-5000', $response->json('data.0.sku_code'));
    }

    /**
     * Filter produk berdasarkan product_type (prepaid vs postpaid).
     */
    public function test_products_list_filter_by_product_type(): void
    {
        Product::create([
            'provider_id' => $this->telkomsel->id,
            'category_id' => $this->pulsaCategory->id,
            'sku_code' => 'TLS-5000',
            'name' => 'Telkomsel 5.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 500000,
            'admin_fee_cents' => 50000,
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $this->pln->id,
            'category_id' => $this->listrikCategory->id,
            'sku_code' => 'PLN-POSTPAID',
            'name' => 'Tagihan Listrik PLN',
            'product_type' => 'postpaid',
            'base_price_cents' => 0,
            'admin_fee_cents' => 300000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        $responsePrepaid = $this->getJson('/products?product_type=prepaid');
        $responsePrepaid->assertStatus(200);
        $this->assertCount(1, $responsePrepaid->json('data'));
        $this->assertEquals('TLS-5000', $responsePrepaid->json('data.0.sku_code'));

        $responsePostpaid = $this->getJson('/products?product_type=postpaid');
        $responsePostpaid->assertStatus(200);
        $this->assertCount(1, $responsePostpaid->json('data'));
        $this->assertEquals('PLN-POSTPAID', $responsePostpaid->json('data.0.sku_code'));
    }
}
