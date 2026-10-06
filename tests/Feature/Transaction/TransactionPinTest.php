<?php

namespace Tests\Feature\Transaction;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Security\Models\PinVerificationToken;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionPinTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Pin Test User',
            'phone' => '081299990004',
            'email' => 'pintest@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 50000000,
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
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'TLS-5000',
            'name' => 'Pulsa Telkomsel 5.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 550000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);
    }

    public function test_transaction_requires_pin_token_header(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeader('Idempotency-Key', 'IDEMP-'.Str::uuid())
            ->postJson('/transactions', [
                'sku_code' => $this->product->sku_code,
                'customer_number' => '081234567890',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_INVALID',
            ]);
    }

    public function test_transaction_rejects_invalid_pin_token(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => 'nonexistent_token_12345',
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_INVALID',
            ]);
    }

    public function test_transaction_rejects_expired_pin_token(): void
    {
        Sanctum::actingAs($this->user);

        $token = PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => Str::random(40),
            'purpose' => 'transaction',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $token->token,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_EXPIRED',
            ]);
    }

    public function test_transaction_rejects_already_used_pin_token(): void
    {
        Sanctum::actingAs($this->user);

        $token = PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => Str::random(40),
            'purpose' => 'transaction',
            'expires_at' => now()->addMinutes(5),
            'used_at' => now()->subMinute(),
        ]);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $token->token,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_USED',
            ]);
    }

    public function test_pin_token_is_marked_as_used_and_cannot_be_reused(): void
    {
        Sanctum::actingAs($this->user);

        $token = PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => Str::random(40),
            'purpose' => 'transaction',
            'expires_at' => now()->addMinutes(5),
        ]);

        // Transaksi pertama: Sukses
        $response1 = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $token->token,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response1->assertStatus(201);
        $token->refresh();
        $this->assertNotNull($token->used_at);

        // Transaksi kedua dengan token yang sama (key idempotency beda): Ditolak PIN_TOKEN_USED
        $response2 = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $token->token,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response2->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_USED',
            ]);
    }
}
