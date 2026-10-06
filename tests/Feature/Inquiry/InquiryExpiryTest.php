<?php

namespace Tests\Feature\Inquiry;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Inquiry\Models\Inquiry;
use App\Domain\Inquiry\Services\InquiryService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    private Product $product;

    private InquiryService $inquiryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Inquiry Expiry User',
            'phone' => '081299991111',
            'email' => 'inquiryexpiry@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Tagihan Listrik',
            'code' => 'tagihan_listrik',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'PLN',
            'code' => 'pln',
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_pln',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'PLN-POSTPAID',
            'name' => 'Tagihan Listrik PLN Pasca',
            'product_type' => 'postpaid',
            'base_price_cents' => 0,
            'admin_fee_cents' => 300000,
            'is_active' => true,
        ]);

        $this->inquiryService = app(InquiryService::class);
    }

    /**
     * Hasil inquiry kedaluwarsa setelah 10 menit (isExpired() menjadi true).
     */
    public function test_inquiry_expires_after_10_minutes(): void
    {
        $inquiry = $this->inquiryService->inquiry($this->user, $this->product, '512345678901');

        // Saat baru dibuat: Belum kedaluwarsa dan valid untuk digunakan
        $this->assertFalse($inquiry->isExpired());
        $this->assertTrue($inquiry->isUsable());

        // Simulasi berjalan 11 menit ke masa depan
        $this->travel(11)->minutes();

        // Setelah 11 menit: Status menjadi expired dan tidak lagi usable
        $this->assertTrue($inquiry->isExpired());
        $this->assertFalse($inquiry->isUsable());
    }

    /**
     * Inquiry dengan status gagal tidak usable meskipun belum expired.
     */
    public function test_failed_inquiry_is_not_usable(): void
    {
        $inquiry = Inquiry::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '512345678901',
            'amount_cents' => 1000000,
            'admin_fee_cents' => 100000,
            'status' => Inquiry::STATUS_FAILED,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertFalse($inquiry->isExpired());
        $this->assertFalse($inquiry->isUsable());
    }
}
