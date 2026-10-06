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

class TransactionIdempotencyTest extends TestCase
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
            'name' => 'Idempotency User',
            'phone' => '081299990001',
            'email' => 'idempotency@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 10000000, // Rp 100.000,00
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
            'sku_code' => 'TLS-10000',
            'name' => 'Pulsa Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1050000, // Rp 10.500,00
            'admin_fee_cents' => 0,
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

    public function test_transaction_requires_idempotency_key_header(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $response = $this->withHeader('X-Pin-Token', $pinToken)
            ->postJson('/transactions', [
                'sku_code' => $this->product->sku_code,
                'customer_number' => '081234567890',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ]);
    }

    public function test_duplicate_idempotency_key_returns_409_conflict(): void
    {
        Sanctum::actingAs($this->user);
        $idempotencyKey = 'IDEMP-'.Str::uuid();

        $pinToken1 = $this->createPinToken($this->user);

        // Request 1: Sukses 201 Created
        $response1 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
            'X-Pin-Token' => $pinToken1,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_number' => '081234567890',
                    'status' => 'pending',
                ],
            ]);

        $originalTxId = $response1->json('data.id');

        // Request 2: Kirim ulang dengan Idempotency-Key yang sama (walaupun header sama)
        $response2 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
            'X-Pin-Token' => $pinToken1,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        // Harus ditolak dengan HTTP 409 IDEMPOTENCY_CONFLICT
        $response2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_CONFLICT',
            ]);

        // Response data berisi response asli dari request pertama
        $this->assertEquals($originalTxId, $response2->json('data.data.id'));

        // Saldo dompet hanya didebit SEKALI
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(10000000 - 1050000, $wallet->balance_cents->toCents());
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_different_user_can_use_same_idempotency_key(): void
    {
        Sanctum::actingAs($this->user);
        $idempotencyKey = 'COMMON-KEY-'.Str::uuid();
        $pinToken1 = $this->createPinToken($this->user);

        $response1 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
            'X-Pin-Token' => $pinToken1,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);
        $response1->assertStatus(201);

        // User B
        $userB = User::create([
            'user_tier_id' => $this->user->user_tier_id,
            'name' => 'User B',
            'phone' => '081299990002',
            'email' => 'userb@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);
        Wallet::create([
            'user_id' => $userB->id,
            'balance_cents' => 10000000,
        ]);

        Sanctum::actingAs($userB);
        $pinToken2 = $this->createPinToken($userB);

        $response2 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
            'X-Pin-Token' => $pinToken2,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567899',
        ]);

        $response2->assertStatus(201);
        $this->assertDatabaseCount('transactions', 2);
    }
}
