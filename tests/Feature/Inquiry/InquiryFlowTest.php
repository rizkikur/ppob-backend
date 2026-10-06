<?php

namespace Tests\Feature\Inquiry;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Inquiry\Models\Inquiry;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    private Product $plnProduct;

    private Product $pdamProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Inquiry Flow User',
            'phone' => '081288880001',
            'email' => 'inquiryflow@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $plnCategory = ProductCategory::create([
            'name' => 'Tagihan Listrik',
            'code' => 'tagihan_listrik',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $pdamCategory = ProductCategory::create([
            'name' => 'PDAM',
            'code' => 'pdam',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $plnProvider = Provider::create([
            'name' => 'PLN',
            'code' => 'pln',
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_pln',
            'is_active' => true,
        ]);

        $pdamProvider = Provider::create([
            'name' => 'PDAM',
            'code' => 'pdam',
            'driver' => 'PdamDriver',
            'queue_name' => 'supplier_pdam',
            'is_active' => true,
        ]);

        $this->plnProduct = Product::create([
            'provider_id' => $plnProvider->id,
            'category_id' => $plnCategory->id,
            'sku_code' => 'PLN-POSTPAID',
            'name' => 'Tagihan Listrik PLN Pasca',
            'product_type' => 'postpaid',
            'base_price_cents' => 0,
            'admin_fee_cents' => 300000, // Rp 3.000,00 admin fee
            'is_active' => true,
        ]);

        $this->pdamProduct = Product::create([
            'provider_id' => $pdamProvider->id,
            'category_id' => $pdamCategory->id,
            'sku_code' => 'PDAM-BDG',
            'name' => 'PDAM Kota Bandung',
            'product_type' => 'postpaid',
            'base_price_cents' => 0,
            'admin_fee_cents' => 250000, // Rp 2.500,00 admin fee
            'is_active' => true,
        ]);
    }

    /**
     * Alur inquiry berhasil untuk tagihan listrik PLN postpaid.
     */
    public function test_inquiry_success_for_pln_postpaid(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'PLN-POSTPAID',
            'customer_number' => '512345678901',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_number' => '512345678901',
                    'amount' => 25000000, // Rp 250.000,00
                    'amount_display' => 'Rp 250.000,00',
                    'admin_fee' => 300000,   // Rp 3.000,00
                    'admin_fee_display' => 'Rp 3.000,00',
                    'status' => 'success',
                ],
            ]);

        $inquiryId = $response->json('data.id');
        $this->assertNotNull($inquiryId);
        $this->assertNotNull($response->json('data.expires_at'));

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiryId,
            'user_id' => $this->user->id,
            'product_id' => $this->plnProduct->id,
            'customer_number' => '512345678901',
            'amount_cents' => 25000000,
            'admin_fee_cents' => 300000,
            'status' => 'success',
        ]);
    }

    /**
     * Alur inquiry berhasil untuk tagihan air PDAM postpaid.
     */
    public function test_inquiry_success_for_pdam_postpaid(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'PDAM-BDG',
            'customer_number' => '0987654321',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_number' => '0987654321',
                    'amount' => 17500000, // Rp 175.000,00
                    'amount_display' => 'Rp 175.000,00',
                    'admin_fee' => 250000,   // Rp 2.500,00
                    'admin_fee_display' => 'Rp 2.500,00',
                    'status' => 'success',
                ],
            ]);

        $this->assertDatabaseHas('inquiries', [
            'user_id' => $this->user->id,
            'product_id' => $this->pdamProduct->id,
            'customer_number' => '0987654321',
            'status' => 'success',
        ]);
    }

    /**
     * Pengujian perhitungan totalAmount() pada model Inquiry.
     */
    public function test_inquiry_model_total_amount_calculation(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/inquiry', [
            'sku_code' => 'PLN-POSTPAID',
            'customer_number' => '512345678901',
        ]);

        $inquiryId = $response->json('data.id');
        /** @var Inquiry $inquiry */
        $inquiry = Inquiry::findOrFail($inquiryId);

        // 25.000.000 cents (Rp 250.000) + 300.000 cents (Rp 3.000) = 25.300.000 cents (Rp 253.000)
        $total = $inquiry->totalAmount();
        $this->assertEquals(25300000, $total->toCents());
        $this->assertEquals('Rp 253.000,00', $total->format());
    }
}
