<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionEventStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealtimeStreamTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private User $user;
    private Product $product;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->admin = Admin::create([
            'name' => 'Ops Admin',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'gold'],
            ['label' => 'Gold Member', 'description' => 'Gold Tier']
        );

        $this->user = User::create([
            'name' => 'Test Customer',
            'phone' => '081299998888',
            'email' => 'customer@test.com',
            'password' => Hash::make('secret123'),
            'user_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'base_url' => 'https://api.digiflazz.com',
            'is_active' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa Reguler',
            'code' => 'PULSA',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'TLKM10',
            'name' => 'Telkomsel 10k',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_dashboard_stream_requires_authentication(): void
    {
        $response = $this->get(route('admin.dashboard.stream'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_dashboard_stream_returns_sse_headers_and_initial_event(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard.stream', ['duration' => 1]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('event: initial', $content);
        $this->assertStringContainsString('metrics', $content);
    }

    public function test_transactions_stream_requires_authentication(): void
    {
        $response = $this->get(route('admin.transactions.stream'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_transactions_stream_returns_sse_headers_and_connected_event(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.transactions.stream', ['duration' => 1, 'status' => 'pending']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('event: connected', $content);
        $this->assertStringContainsString('status":"connected"', $content);
        $this->assertStringContainsString('pending', $content);
    }

    public function test_transaction_observer_broadcasts_created_and_updated_events(): void
    {
        $initialSeq = (int) Cache::get('sse:event:seq', 0);

        // 1. Created transaction triggers broadcast
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->provider->id,
            'customer_number' => '081299998888',
            'idempotency_key' => 'idem-sse-test-1',
            'status' => 'pending',
            'amount_cents' => Money::fromRupiah(10000),
            'sell_price_cents' => Money::fromRupiah(10500),
        ]);

        $this->assertGreaterThan($initialSeq, (int) Cache::get('sse:event:seq', 0));
        $latest = Cache::get('sse:event:latest');
        $this->assertNotNull($latest);
        $this->assertEquals($transaction->id, $latest['id']);
        $this->assertEquals('pending', $latest['status']);
        $this->assertEquals('created', $latest['action']);
        $this->assertEquals('081299998888', $latest['customer_number']);

        // 2. Updated status triggers broadcast
        $seqBeforeUpdate = (int) Cache::get('sse:event:seq');
        $transaction->update(['status' => 'success']);

        $this->assertGreaterThan($seqBeforeUpdate, (int) Cache::get('sse:event:seq'));
        $updatedEvent = Cache::get('sse:event:latest');
        $this->assertEquals('success', $updatedEvent['status']);
        $this->assertEquals('pending', $updatedEvent['old_status']);
        $this->assertEquals('updated', $updatedEvent['action']);
    }

    public function test_streamer_filter_matching_logic(): void
    {
        /** @var TransactionEventStreamer $streamer */
        $streamer = app(TransactionEventStreamer::class);

        $event = [
            'id' => 1234,
            'status' => 'pending',
            'old_status' => null,
            'customer_number' => '081234567890',
            'product_name' => 'Telkomsel 50k',
            'product_sku' => 'TSEL50',
            'provider_id' => 10,
            'provider_name' => 'Digiflazz',
            'provider_ref' => 'REF-999',
            'is_failover' => false,
        ];

        // Status match
        $this->assertTrue($streamer->matchesFilter($event, ['status' => 'pending']));
        $this->assertFalse($streamer->matchesFilter($event, ['status' => 'success']));

        // Bi-directional match: event was pending, now success -> should match if client filtered pending
        $eventUpdated = $event;
        $eventUpdated['status'] = 'success';
        $eventUpdated['old_status'] = 'pending';
        $this->assertTrue($streamer->matchesFilter($eventUpdated, ['status' => 'pending']));

        // Provider match
        $this->assertTrue($streamer->matchesFilter($event, ['provider_id' => 10]));
        $this->assertFalse($streamer->matchesFilter($event, ['provider_id' => 99]));

        // Failover match
        $this->assertTrue($streamer->matchesFilter($event, ['is_failover' => '0']));
        $this->assertFalse($streamer->matchesFilter($event, ['is_failover' => '1']));

        // Query match
        $this->assertTrue($streamer->matchesFilter($event, ['q' => '1234']));
        $this->assertTrue($streamer->matchesFilter($event, ['q' => '081234567890']));
        $this->assertTrue($streamer->matchesFilter($event, ['q' => 'TSEL50']));
        $this->assertFalse($streamer->matchesFilter($event, ['q' => 'indosat']));
    }
}
