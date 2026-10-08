<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Jobs\DeliverWebhookJob;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PartnerWebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private Product $product;

    private string $rawSecret = 'secret_partner_webhook';

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10K',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);

        $this->partner = Partner::create([
            'name' => 'PT Mitra Terpadu',
            'api_key' => 'key_partner_webhook',
            'secret' => encrypt($this->rawSecret),
            'response_mode' => 'async',
            'callback_url' => 'https://partner.example.com/api/callback',
            'callback_secret' => 'secret_callback_123',
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

    private function partnerHeaders(Partner $partner, string $method, string $path, array $body = []): array
    {
        $ts = (string) now()->timestamp;
        $rawBody = ! empty($body) ? json_encode($body) : '';
        $sig = $this->generateSignature($partner, $method, $path, $ts, $rawBody);

        return [
            'X-Api-Key' => $partner->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
        ];
    }

    public function test_successful_webhook_delivery_when_transaction_succeeds(): void
    {
        Queue::fake([ProcessTransactionJob::class, DeliverWebhookJob::class]);

        Http::fake([
            'https://partner.example.com/*' => Http::response(['success' => true], 200),
        ]);

        $partnerUser = $this->partner->getOrCreateUser();
        $wallet = Wallet::where('user_id', $partnerUser->id)->first();
        $wallet->update(['balance_cents' => 5000000]);

        /** @var TransactionService $txService */
        $txService = app(TransactionService::class);
        $tx = $txService->createForPartner($this->partner, $this->product, '081234567890');

        // Tandai transaksi sukses
        $txService->markSuccess($tx, 'SN-TSEL-9999');

        $delivery = WebhookDelivery::where('transaction_id', $tx->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals(WebhookDelivery::STATUS_PENDING, $delivery->status);

        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });

        // Jalankan Job pengiriman webhook
        $job = new DeliverWebhookJob($delivery->id);
        $job->handle();

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertEquals(200, $delivery->response_code);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNull($delivery->next_retry_at);

        Http::assertSent(function (HttpClientRequest $request) {
            $hasValidSignature = $request->hasHeader('X-Signature');
            $hasTimestamp = $request->hasHeader('X-Timestamp');
            $data = $request->data();

            return $request->url() === 'https://partner.example.com/api/callback'
                && $hasValidSignature
                && $hasTimestamp
                && $data['event'] === 'transaction.updated'
                && $data['data']['status'] === 'success'
                && $data['data']['sn'] === 'SN-TSEL-9999';
        });
    }

    public function test_webhook_retry_on_client_failure_with_exponential_backoff(): void
    {
        Queue::fake([DeliverWebhookJob::class]);

        Http::fake([
            'https://partner.example.com/*' => Http::response('Server Error', 500),
        ]);

        $tx = Transaction::create([
            'user_id' => $this->partner->getOrCreateUser()->id,
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1000000,
            'status' => Transaction::STATUS_PROCESSING,
            'idempotency_key' => 'idemp_test_retry',
        ]);

        $delivery = WebhookDelivery::create([
            'partner_id' => $this->partner->id,
            'transaction_id' => $tx->id,
            'attempt' => 1,
            'status' => WebhookDelivery::STATUS_PENDING,
            'callback_url' => $this->partner->callback_url,
        ]);

        $job = new DeliverWebhookJob($delivery->id);
        $job->handle();

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertEquals(2, $delivery->attempt);
        $this->assertEquals(500, $delivery->response_code);
        $this->assertNotNull($delivery->next_retry_at);

        // Verifikasi attempt 2 di-dispatch dengan delay
        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $queuedJob) use ($delivery) {
            return $queuedJob->deliveryId === $delivery->id
                && $queuedJob->delay !== null;
        });
    }

    public function test_webhook_marked_failed_permanent_after_max_attempts(): void
    {
        Queue::fake([DeliverWebhookJob::class]);

        Http::fake([
            'https://partner.example.com/*' => Http::response('Gateway Timeout', 504),
        ]);

        $tx = Transaction::create([
            'user_id' => $this->partner->getOrCreateUser()->id,
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567890',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1000000,
            'status' => Transaction::STATUS_PROCESSING,
            'idempotency_key' => 'idemp_test_perm_fail',
        ]);

        $delivery = WebhookDelivery::create([
            'partner_id' => $this->partner->id,
            'transaction_id' => $tx->id,
            'attempt' => 5, // Attempt ke-5 (maksimal)
            'status' => WebhookDelivery::STATUS_FAILED,
            'callback_url' => $this->partner->callback_url,
        ]);

        $job = new DeliverWebhookJob($delivery->id);
        $job->handle();

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_FAILED_PERMANENT, $delivery->status);
        $this->assertEquals(504, $delivery->response_code);
        $this->assertNull($delivery->next_retry_at);

        // Tidak ada dispatch baru setelah attempt 5 gagal
        Queue::assertNothingPushed();
    }

    public function test_partner_async_response_mode_returns_202_accepted(): void
    {
        $partnerUser = $this->partner->getOrCreateUser();
        $wallet = Wallet::where('user_id', $partnerUser->id)->first();
        $wallet->update(['balance_cents' => 5000000]);

        $body = [
            'sku_code' => 'TSEL10K',
            'customer_number' => '081234567890',
        ];
        $headers = $this->partnerHeaders($this->partner, 'POST', '/partner/transactions', $body);
        $headers['Idempotency-Key'] = 'idemp_async_test_001';

        $response = $this->withHeaders($headers)->postJson('/partner/transactions', $body);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'customer_number' => '081234567890',
                ],
            ]);
    }

    public function test_webhook_delivered_on_transaction_fail_and_refund(): void
    {
        Queue::fake([ProcessTransactionJob::class, DeliverWebhookJob::class]);

        Http::fake([
            'https://partner.example.com/*' => Http::response(['received' => true], 200),
        ]);

        $partnerUser = $this->partner->getOrCreateUser();
        $wallet = Wallet::where('user_id', $partnerUser->id)->first();
        $wallet->update(['balance_cents' => 5000000]);

        /** @var TransactionService $txService */
        $txService = app(TransactionService::class);
        $tx = $txService->createForPartner($this->partner, $this->product, '081234567890');

        // Gagal & refund
        $txService->failAndRefund($tx, 'Nomor HP tidak aktif');

        $delivery = WebhookDelivery::where('transaction_id', $tx->id)->first();
        $this->assertNotNull($delivery);
        $this->assertCount(1, $tx->webhookDeliveries);
        $this->assertEquals($delivery->id, $tx->webhookDeliveries->first()->id);

        $job = new DeliverWebhookJob($delivery->id);
        $job->handle();

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_DELIVERED, $delivery->status);

        Http::assertSent(function (HttpClientRequest $request) {
            $data = $request->data();

            return $data['data']['status'] === 'failed'
                && $data['data']['failure_reason'] === 'Nomor HP tidak aktif';
        });
    }

    public function test_retry_failed_webhooks_artisan_command(): void
    {
        Queue::fake([DeliverWebhookJob::class]);

        $delivery = WebhookDelivery::create([
            'partner_id' => $this->partner->id,
            'transaction_id' => 888,
            'attempt' => 2,
            'status' => WebhookDelivery::STATUS_FAILED,
            'callback_url' => $this->partner->callback_url,
            'next_retry_at' => now()->subMinutes(5),
        ]);

        $this->artisan('ppob:webhook:retry')
            ->assertSuccessful();

        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });
    }
}
