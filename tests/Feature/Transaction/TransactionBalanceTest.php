<?php

namespace Tests\Feature\Transaction;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Security\Models\PinVerificationToken;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionBalanceTest extends TestCase
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
            'name' => 'Balance User',
            'phone' => '081299990003',
            'email' => 'balance@example.com',
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
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'TLS-50000',
            'name' => 'Pulsa Telkomsel 50.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 5050000, // Rp 50.500,00
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

    public function test_transaction_fails_if_balance_insufficient(): void
    {
        // Saldo hanya Rp 10.000, harga produk Rp 50.500
        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 1000000,
        ]);

        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

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
                'error_code' => 'INSUFFICIENT_BALANCE',
            ]);

        // Tidak ada transaksi yang tersimpan
        $this->assertDatabaseCount('transactions', 0);

        // Saldo tidak berkurang
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(1000000, $wallet->balance_cents->toCents());
    }

    public function test_transaction_succeeds_with_sufficient_balance(): void
    {
        // Saldo Rp 100.000, harga produk Rp 50.500
        $wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 10000000,
        ]);

        Sanctum::actingAs($this->user);
        $pinToken = $this->createPinToken($this->user);

        $response = $this->withHeaders([
            'Idempotency-Key' => 'IDEMP-'.Str::uuid(),
            'X-Pin-Token' => $pinToken,
        ])->postJson('/transactions', [
            'sku_code' => $this->product->sku_code,
            'customer_number' => '081234567890',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'sell_price' => 5050000,
                    'status' => 'pending',
                ],
            ]);

        // Saldo berkurang menjadi Rp 49.500
        $wallet->refresh();
        $this->assertEquals(10000000 - 5050000, $wallet->balance_cents->toCents());

        // Mutasi tercatat rapi
        $txId = $response->json('data.id');
        $mutation = WalletMutation::where('user_id', $this->user->id)
            ->where('reference_id', $txId)
            ->first();

        $this->assertNotNull($mutation);
        $this->assertEquals(WalletMutation::TYPE_DEBIT, $mutation->type);
        $this->assertEquals(5050000, $mutation->amount_cents->toCents());
        $this->assertEquals(4950000, $mutation->balance_after_cents->toCents());
        $this->assertEquals(WalletMutation::REF_TRANSACTION, $mutation->reference_type);
    }
}
