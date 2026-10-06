<?php

namespace Tests\Feature\Ppob;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private string $secret = 'test_webhook_secret_key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ppob.providers.pln.webhook_secret' => $this->secret]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Webhook User',
            'phone' => '081277770001',
            'email' => 'webhook@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 10000000,
        ]);

        $category = ProductCategory::create([
            'name' => 'Listrik',
            'code' => 'listrik',
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
            'sku_code' => 'PLN-PRE-10K',
            'name' => 'Token PLN 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1050000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);
    }

    private function generateSignature(array $payload, ?string $secret = null): string
    {
        $raw = json_encode($payload);

        return hash_hmac('sha256', $raw, $secret ?? $this->secret);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $payload = [
            'event_id' => 'EVT-'.Str::uuid(),
            'status' => 'success',
        ];

        $response = $this->withHeaders([
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_SIGNATURE_INVALID',
            ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'event_id' => 'EVT-'.Str::uuid(),
            'status' => 'success',
        ];

        $invalidSignature = $this->generateSignature($payload, 'wrong_secret_key');

        $response = $this->withHeaders([
            'X-Signature' => $invalidSignature,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_SIGNATURE_INVALID',
            ]);
    }

    public function test_webhook_rejects_stale_timestamp(): void
    {
        $payload = [
            'event_id' => 'EVT-'.Str::uuid(),
            'status' => 'success',
        ];

        $signature = $this->generateSignature($payload);

        // Timestamp 301 detik yang lalu (di luar toleransi 300 detik)
        $staleTimestamp = now()->subSeconds(301)->timestamp;

        $response = $this->withHeaders([
            'X-Signature' => $signature,
            'X-Timestamp' => (string) $staleTimestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_TIMESTAMP_INVALID',
            ]);
    }

    public function test_webhook_rejects_missing_event_id(): void
    {
        $payload = [
            'status' => 'success',
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->withHeaders([
            'X-Signature' => $signature,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_webhook_rejects_duplicate_event_id_with_409_conflict(): void
    {
        $eventId = 'EVT-DUP-'.Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'status' => 'success',
        ];

        $signature = $this->generateSignature($payload);

        // Request 1: Sukses
        $response1 = $this->withHeaders([
            'X-Signature' => $signature,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Request 2: Duplikat event_id harus ditolak dengan 409 WEBHOOK_DUPLICATE
        $response2 = $this->withHeaders([
            'X-Signature' => $signature,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_DUPLICATE',
            ]);
    }

    public function test_webhook_processes_valid_request_and_updates_transaction_status(): void
    {
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 1050000,
            'sell_price_cents' => 1050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-PLN-ASYNC-01',
            'idempotency_key' => 'TX-WH-01',
        ]);

        $payload = [
            'event_id' => 'EVT-SUCCESS-'.Str::uuid(),
            'transaction_id' => $tx->id,
            'provider_ref' => 'REF-PLN-ASYNC-01',
            'status' => 'success',
            'serial_number' => '1234-5678-9012-3456',
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->withHeaders([
            'X-Signature' => $signature,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $tx->status);
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => $payload['event_id'],
            'source' => 'pln',
        ]);
    }

    public function test_webhook_failure_status_triggers_auto_refund(): void
    {
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 1050000,
            'sell_price_cents' => 1050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-PLN-FAIL-01',
            'idempotency_key' => 'TX-WH-02',
        ]);

        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $balanceBefore = $wallet->balance_cents->toCents();

        $payload = [
            'event_id' => 'EVT-FAIL-'.Str::uuid(),
            'transaction_id' => $tx->id,
            'status' => 'failed',
            'failure_reason' => 'Kwh meter salah / tidak ditemukan',
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->withHeaders([
            'X-Signature' => $signature,
            'X-Timestamp' => (string) now()->timestamp,
            'X-Provider' => 'pln',
        ])->postJson('/ppob/callback', $payload);

        $response->assertStatus(200);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $tx->status);

        // Saldo user harus bertambah karena di-refund otomatis
        $wallet->refresh();
        $this->assertEquals($balanceBefore + 1050000, $wallet->balance_cents->toCents());

        // Ada mutasi refund
        $this->assertDatabaseHas('wallet_mutations', [
            'user_id' => $this->user->id,
            'reference_type' => WalletMutation::REF_REFUND,
            'reference_id' => $tx->id,
        ]);
    }
}
