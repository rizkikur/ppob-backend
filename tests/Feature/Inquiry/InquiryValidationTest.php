<?php

namespace Tests\Feature\Inquiry;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    private Product $prepaidProduct;

    private Product $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Inquiry User',
            'phone' => '081266661111',
            'email' => 'inquiry@example.com',
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

        $this->prepaidProduct = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'TLS-5000',
            'name' => 'Pulsa Telkomsel 5.000',
            'product_type' => 'prepaid',
            'base_price_cents' => 500000,
            'admin_fee_cents' => 50000,
            'is_active' => true,
        ]);

        $this->inactiveProduct = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'PLN-OFF',
            'name' => 'PLN Nonaktif',
            'product_type' => 'postpaid',
            'base_price_cents' => 0,
            'admin_fee_cents' => 250000,
            'is_active' => false,
        ]);
    }

    /**
     * ATURAN KERAS: Inquiry wajib ditolak untuk produk prepaid (PRODUCT_TYPE_MISMATCH HTTP 422).
     */
    public function test_inquiry_fails_for_prepaid_sku(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'TLS-5000',
            'customer_number' => '08123456789',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PRODUCT_TYPE_MISMATCH',
            ]);
    }

    /**
     * Inquiry gagal jika SKU tidak ditemukan di database (PRODUCT_NOT_FOUND HTTP 404).
     */
    public function test_inquiry_fails_for_non_existent_sku(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'UNKNOWN-SKU-999',
            'customer_number' => '08123456789',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'PRODUCT_NOT_FOUND',
            ]);
    }

    /**
     * Inquiry gagal jika produk berstatus non-aktif (PRODUCT_INACTIVE HTTP 422).
     */
    public function test_inquiry_fails_for_inactive_product(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'PLN-OFF',
            'customer_number' => '123456789',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PRODUCT_INACTIVE',
            ]);
    }

    /**
     * Validasi gagal jika format input customer_number tidak sesuai (VALIDATION_ERROR HTTP 422).
     */
    public function test_inquiry_fails_when_fields_are_invalid(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'TLS-5000',
            'customer_number' => 'nomor dengan spasi dan @!$',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
            ]);
    }
}
