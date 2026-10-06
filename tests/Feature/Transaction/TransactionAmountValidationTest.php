<?php

namespace Tests\Feature\Transaction;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Inquiry\Models\Inquiry;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Security\Models\PinVerificationToken;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionAmountValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $prepaidProduct;

    private Product $postpaidProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Amount Validation User',
            'phone' => '081299990002',
            'email' => 'amountvalid@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 50000000, // Rp 500.000,00
        ]);

        $category = ProductCategory::create([
            'name' => 'Listrik',
            'code' => 'listrik',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'PLN Provider',
            'code' => 'pln',
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_pln',
            'is_active' => true,
        ]);

        $this->prepaidProduct = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'PLN-PRE-20K',
            'name' => 'Token Listrik 20.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 2050000, // Rp 20.500,00
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);

        $this->postpaidProduct = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'PLN-POSTPAID',
            'name' => 'Tagihan Listrik PLN',
            'product_type' => Product::TYPE_POSTPAID,
            'base_price_cents' => 0,
            'admin_fee_cents' => 250000, // Rp 2.500,00
            'is_active' => true,
        ]);
    }

    private function createPinToken(User $user): string
    {
        $token = Str::random(40);
        PinVerificationToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'purpose' => 'transaction',
            'expires_at' => now()->addMinutes(5),
        ]);

        return $token;
    }

    public function test_prepaid_ignores_client_amount(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        // Klien mengirim amount yang sengaja ngawur (misal Rp 100 atau Rp 99.999.999)
        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->prepaidProduct->sku_code,
            'customer_number' => '081234567890',
            'amount' => 100, // Diabaikan oleh server!
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 2050000,
                    'sell_price' => 2050000,
                    'status' => 'pending',
                ],
            ]);

        // Pastikan saldo terpotong sesuai harga server (Rp 20.500), bukan Rp 1
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(50000000 - 2050000, $wallet->balance_cents->toCents());
    }

    public function test_postpaid_requires_inquiry_id(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->postpaidProduct->sku_code,
            'customer_number' => '1234567890',
            'amount' => 25000000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'INQUIRY_REQUIRED',
            ]);
    }

    public function test_postpaid_rejects_expired_inquiry(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        // Buat inquiry yang sudah kedaluwarsa
        $expiredInquiry = Inquiry::create([
            'user_id' => $this->user->id,
            'product_id' => $this->postpaidProduct->id,
            'customer_number' => '1234567890',
            'amount_cents' => 25000000,
            'admin_fee_cents' => 250000,
            'inquiry_ref' => 'REF-EXP-001',
            'status' => Inquiry::STATUS_SUCCESS,
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->postpaidProduct->sku_code,
            'customer_number' => '1234567890',
            'inquiry_id' => $expiredInquiry->id,
            'amount' => 25000000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'INQUIRY_EXPIRED',
            ]);
    }

    public function test_postpaid_rejects_amount_mismatch(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $validInquiry = Inquiry::create([
            'user_id' => $this->user->id,
            'product_id' => $this->postpaidProduct->id,
            'customer_number' => '1234567890',
            'amount_cents' => 25000000, // Rp 250.000,00
            'admin_fee_cents' => 250000, // Rp 2.500,00
            'inquiry_ref' => 'REF-VAL-001',
            'status' => Inquiry::STATUS_SUCCESS,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Klien kirim amount yang salah (Rp 200.000 bukan Rp 250.000)
        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->postpaidProduct->sku_code,
            'customer_number' => '1234567890',
            'inquiry_id' => $validInquiry->id,
            'amount' => 20000000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'INQUIRY_AMOUNT_MISMATCH',
            ]);
    }

    public function test_postpaid_succeeds_when_amount_matches(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $validInquiry = Inquiry::create([
            'user_id' => $this->user->id,
            'product_id' => $this->postpaidProduct->id,
            'customer_number' => '1234567890',
            'amount_cents' => 25000000, // Rp 250.000,00
            'admin_fee_cents' => 250000, // Rp 2.500,00
            'inquiry_ref' => 'REF-VAL-002',
            'status' => Inquiry::STATUS_SUCCESS,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->postpaidProduct->sku_code,
            'customer_number' => '1234567890',
            'inquiry_id' => $validInquiry->id,
            'amount' => 25000000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 25000000,
                    'sell_price' => 25250000, // Tagihan + admin fee (Rp 252.500,00)
                    'status' => 'pending',
                ],
            ]);

        // Saldo terpotong total Rp 252.500,00
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(50000000 - 25250000, $wallet->balance_cents->toCents());
    }
}
