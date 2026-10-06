<?php

namespace Tests\Feature\Partner;

use App\Domain\Auth\Models\UserTier;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerProductPrice;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PartnerTransactionTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private Product $productPrepaid;

    private Product $productCustomPriced;

    private string $rawSecret = 'partner-tx-secret-999';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        UserTier::firstOrCreate(
            ['name' => 'partner'],
            ['label' => 'Mitra / Partner', 'description' => 'Tier mitra B2B']
        );

        $this->partner = Partner::create([
            'name' => 'PT Gerbang Pembayaran Nusantara',
            'api_key' => 'partner_key_tx_test_001',
            'secret' => encrypt($this->rawSecret),
            'allowed_ips' => null,
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);

        // Isi saldo wallet partner: Rp 500.000,00 (50.000.000 cents)
        $partnerUser = $this->partner->getOrCreateUser();
        Wallet::updateOrCreate(
            ['user_id' => $partnerUser->id],
            ['balance_cents' => 50000000]
        );

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

        // Produk 1: Default pricing (base 20.000 + fee 1.500 = 21.500)
        $this->productPrepaid = Product::create([
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'sku_code' => 'TELKOMSEL20',
            'name' => 'Telkomsel 20K',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 2000000,
            'admin_fee_cents' => 150000,
            'is_active' => true,
        ]);

        // Produk 2: Custom pricing khusus partner via PartnerProductPrice (harga 19.500)
        $this->productCustomPriced = Product::create([
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'sku_code' => 'TELKOMSEL50',
            'name' => 'Telkomsel 50K',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 5000000,
            'admin_fee_cents' => 200000,
            'is_active' => true,
        ]);

        PartnerProductPrice::create([
            'partner_id' => $this->partner->id,
            'product_id' => $this->productCustomPriced->id,
            'sell_price_cents' => 4950000, // Rp 49.500,00 (diskon khusus mitra)
            'is_active' => true,
        ]);
    }

    private function generateSignature(Partner $partner, string $method, string $path, string $timestamp, string $rawBody = ''): string
    {
        $bodyHash = hash('sha256', $rawBody);
        $pathWithSlash = '/'.ltrim($path, '/');
        $stringToSign = strtoupper($method)."\n".$pathWithSlash."\n".$timestamp."\n".$bodyHash;
        $secret = $partner->getSecretDecrypted();

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    private function partnerHeaders(Partner $partner, string $method, string $path, array $body = [], ?string $idempotencyKey = null): array
    {
        $ts = (string) now()->timestamp;
        $rawBody = ! empty($body) ? json_encode($body) : '';
        $sig = $this->generateSignature($partner, $method, $path, $ts, $rawBody);

        $headers = [
            'X-Api-Key' => $partner->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    public function test_get_balance_returns_partner_wallet_balance(): void
    {
        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance');
        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'partner_id' => $this->partner->id,
                    'partner_name' => $this->partner->name,
                    'balance' => 50000000,
                    'currency' => 'IDR',
                ],
            ]);
    }

    public function test_get_products_applies_custom_partner_pricing_and_fallback(): void
    {
        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/products');
        $response = $this->getJson('/partner/products', $headers);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Produk 1: Fallback ke base + fee (20.000 + 1.500 = 21.500 -> 2.150.000 cents)
        $item1 = collect($data)->firstWhere('sku_code', 'TELKOMSEL20');
        $this->assertEquals(2150000, $item1['price']);

        // Produk 2: Custom partner price dari tabel partner_product_prices (49.500 -> 4.950.000 cents)
        $item2 = collect($data)->firstWhere('sku_code', 'TELKOMSEL50');
        $this->assertEquals(4950000, $item2['price']);
    }

    public function test_create_transaction_requires_idempotency_key(): void
    {
        $payload = [
            'sku_code' => 'TELKOMSEL20',
            'customer_number' => '081234567890',
        ];

        // Tanpa header Idempotency-Key
        $headers = $this->partnerHeaders($this->partner, 'POST', '/partner/transactions', $payload);
        $response = $this->postJson('/partner/transactions', $payload, $headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ]);
    }

    public function test_create_transaction_success_and_debits_partner_wallet(): void
    {
        $payload = [
            'sku_code' => 'TELKOMSEL50',
            'customer_number' => '081234567890',
        ];

        $headers = $this->partnerHeaders(
            $this->partner,
            'POST',
            '/partner/transactions',
            $payload,
            'idemp-partner-tx-001'
        );

        $response = $this->postJson('/partner/transactions', $payload, $headers);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'partner_id' => $this->partner->id,
                    'sku_code' => 'TELKOMSEL50',
                    'customer_number' => '081234567890',
                    'price' => 4950000, // Harga khusus mitra
                    'status' => 'pending',
                    'idempotency_key' => 'idemp-partner-tx-001',
                ],
            ]);

        // Verifikasi saldo mitra berkurang sesuai harga khusus mitra (500.000 - 49.500 = 450.500 -> 45.050.000 cents)
        $partnerUser = $this->partner->getOrCreateUser();
        $wallet = Wallet::where('user_id', $partnerUser->id)->first();
        $this->assertEquals(45050000, $wallet->balance_cents->toCents());

        // Verifikasi transaksi tercatat di database dengan partner_id
        $this->assertDatabaseHas('transactions', [
            'partner_id' => $this->partner->id,
            'user_id' => $partnerUser->id,
            'product_id' => $this->productCustomPriced->id,
            'sell_price_cents' => 4950000,
            'status' => 'pending',
            'idempotency_key' => 'idemp-partner-tx-001',
        ]);

        // Verifikasi audit log tercatat
        $this->assertDatabaseHas('partner_logs', [
            'partner_id' => $this->partner->id,
            'method' => 'POST',
            'response_code' => 201,
        ]);

        // Verifikasi job dikirim ke queue supplier
        Queue::assertPushedOn('supplier_telkomsel', ProcessTransactionJob::class);
    }

    public function test_create_transaction_idempotency_replay_returns_409(): void
    {
        $payload = [
            'sku_code' => 'TELKOMSEL20',
            'customer_number' => '081234567890',
        ];

        $headers = $this->partnerHeaders(
            $this->partner,
            'POST',
            '/partner/transactions',
            $payload,
            'idemp-replay-key-002'
        );

        // Request pertama: Berhasil (201)
        $resp1 = $this->postJson('/partner/transactions', $payload, $headers);
        $resp1->assertStatus(201);

        // Request kedua dengan key yang sama: Ditolak dengan 409 IDEMPOTENCY_CONFLICT
        $resp2 = $this->postJson('/partner/transactions', $payload, $headers);
        $resp2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_CONFLICT',
            ]);
    }

    public function test_create_transaction_insufficient_balance_returns_422(): void
    {
        // Kurangi saldo mitra menjadi hanya Rp 10.000,00
        $partnerUser = $this->partner->getOrCreateUser();
        Wallet::where('user_id', $partnerUser->id)->update(['balance_cents' => 1000000]);

        $payload = [
            'sku_code' => 'TELKOMSEL50', // Harga Rp 49.500
            'customer_number' => '081234567890',
        ];

        $headers = $this->partnerHeaders(
            $this->partner,
            'POST',
            '/partner/transactions',
            $payload,
            'idemp-insufficient-003'
        );

        $response = $this->postJson('/partner/transactions', $payload, $headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'INSUFFICIENT_BALANCE',
            ]);
    }

    public function test_get_transaction_by_id_and_by_idempotency_key(): void
    {
        $payload = [
            'sku_code' => 'TELKOMSEL20',
            'customer_number' => '081234567890',
        ];

        $headersPost = $this->partnerHeaders(
            $this->partner,
            'POST',
            '/partner/transactions',
            $payload,
            'idemp-get-tx-004'
        );

        $createResp = $this->postJson('/partner/transactions', $payload, $headersPost);
        $createResp->assertStatus(201);
        $txId = (string) $createResp->json('data.id');

        // 1. Ambil via numeric ID
        $headersGet1 = $this->partnerHeaders($this->partner, 'GET', "/partner/transactions/{$txId}");
        $getResp1 = $this->getJson("/partner/transactions/{$txId}", $headersGet1);

        $getResp1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => (int) $txId,
                    'partner_id' => $this->partner->id,
                    'sku_code' => 'TELKOMSEL20',
                    'idempotency_key' => 'idemp-get-tx-004',
                ],
            ]);

        // 2. Ambil via Idempotency-Key
        $headersGet2 = $this->partnerHeaders($this->partner, 'GET', '/partner/transactions/idemp-get-tx-004');
        $getResp2 = $this->getJson('/partner/transactions/idemp-get-tx-004', $headersGet2);

        $getResp2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => (int) $txId,
                    'idempotency_key' => 'idemp-get-tx-004',
                ],
            ]);
    }

    public function test_get_transaction_belongs_to_other_partner_returns_404(): void
    {
        $otherPartner = Partner::create([
            'name' => 'PT Mitra Lain',
            'api_key' => 'partner_key_other_tx',
            'secret' => encrypt('other-secret-12345'),
            'allowed_ips' => null,
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);

        $payload = [
            'sku_code' => 'TELKOMSEL20',
            'customer_number' => '081234567890',
        ];

        $headersPost = $this->partnerHeaders(
            $this->partner,
            'POST',
            '/partner/transactions',
            $payload,
            'idemp-isolated-tx-005'
        );

        $createResp = $this->postJson('/partner/transactions', $payload, $headersPost);
        $createResp->assertStatus(201);
        $txId = (string) $createResp->json('data.id');

        // Other partner mencoba mengakses transaksi partner pertama
        $headersOther = $this->partnerHeaders($otherPartner, 'GET', "/partner/transactions/{$txId}");
        $respOther = $this->getJson("/partner/transactions/{$txId}", $headersOther);

        $respOther->assertStatus(404);
    }
}
