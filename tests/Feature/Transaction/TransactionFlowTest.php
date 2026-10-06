<?php

namespace Tests\Feature\Transaction;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Security\Models\PinVerificationToken;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionFlowTest extends TestCase
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
            'name' => 'Flow User',
            'phone' => '081299990005',
            'email' => 'flowuser@example.com',
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

    public function test_create_transaction_and_view_detail(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(201);
        $txId = $response->json('data.id');

        Queue::assertPushed(ProcessTransactionJob::class);

        // GET /transactions/{id}
        $detailResponse = $this->getJson("/transactions/{$txId}");
        $detailResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $txId,
                    'customer_number' => '081234567890',
                    'amount' => 550000,
                    'sell_price' => 550000,
                    'status' => 'pending',
                    'product' => [
                        'sku_code' => 'TLS-5000',
                        'name' => 'Pulsa Telkomsel 5.000',
                    ],
                ],
            ]);
    }

    public function test_list_transactions_history_with_status_filter(): void
    {
        Sanctum::actingAs($this->user);

        // Buat 3 transaksi: 2 success, 1 pending
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567891',
            'amount_cents' => 550000,
            'sell_price_cents' => 550000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'TX-01',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567892',
            'amount_cents' => 550000,
            'sell_price_cents' => 550000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'TX-02',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567893',
            'amount_cents' => 550000,
            'sell_price_cents' => 550000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'TX-03',
        ]);

        // GET all
        $allResponse = $this->getJson('/transactions');
        $allResponse->assertStatus(200);
        $this->assertCount(3, $allResponse->json('data'));

        // GET filtered by status=success
        $filteredResponse = $this->getJson('/transactions?status=success');
        $filteredResponse->assertStatus(200);
        $this->assertCount(2, $filteredResponse->json('data'));

        // GET filtered by status=pending
        $pendingResponse = $this->getJson('/transactions?status=pending');
        $pendingResponse->assertStatus(200);
        $this->assertCount(1, $pendingResponse->json('data'));
    }

    public function test_cannot_view_other_users_transaction(): void
    {
        // Transaksi milik user A
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567891',
            'amount_cents' => 550000,
            'sell_price_cents' => 550000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'TX-USER-A',
        ]);

        // User B login
        $userB = User::create([
            'user_tier_id' => $this->user->user_tier_id,
            'name' => 'User B',
            'phone' => '081299990006',
            'email' => 'userbflow@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);
        Sanctum::actingAs($userB);

        $response = $this->getJson("/transactions/{$tx->id}");
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'TRANSACTION_NOT_FOUND',
            ]);
    }

    public function test_inactive_product_rejected(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $this->product->update(['is_active' => false]);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PRODUCT_INACTIVE',
            ]);
    }

    public function test_nonexistent_product_rejected(): void
    {
        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => 'SKU-TIDAK-ADA',
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'PRODUCT_NOT_FOUND',
            ]);
    }
}
