<?php

namespace Tests\Feature\Admin;

use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerRoutingRule;
use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Ppob\Services\SupplierRoutingService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Services\OperationalAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OperationalAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_alert_sends_to_telegram_with_correct_payload(): void
    {
        config([
            'ppob.alerts.enabled' => true,
            'ppob.alerts.channel' => 'telegram',
            'ppob.alerts.cooldown_seconds' => 300,
            'ppob.alerts.telegram.bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'ppob.alerts.telegram.chat_id' => '-100987654321',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $service = new OperationalAlertService();
        $sent = $service->notifyCircuitBreakerTripped('digiflazz', 5, 'Connection timeout ke upstream');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/sendMessage')
                && $request['chat_id'] === '-100987654321'
                && str_contains($request['text'], 'CIRCUIT BREAKER TRIPPED')
                && str_contains($request['text'], 'DIGIFLAZZ')
                && $request['parse_mode'] === 'HTML';
        });
    }

    public function test_alert_sends_to_generic_webhook_with_embeds(): void
    {
        config([
            'ppob.alerts.enabled' => true,
            'ppob.alerts.channel' => 'webhook',
            'ppob.alerts.cooldown_seconds' => 300,
            'ppob.alerts.webhook.url' => 'https://discord.com/api/webhooks/test-hook',
        ]);

        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response(['id' => '123'], 204),
        ]);

        $service = new OperationalAlertService();
        $sent = $service->notifyLowBalance('Digiflazz Gateway', 'DIGIFLAZZ', 250000, 1000000);

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/test-hook'
                && str_contains($request['content'], 'LOW BALANCE ALERT')
                && ! empty($request['embeds']);
        });
    }

    public function test_anti_spam_throttling_suppresses_rapid_duplicate_alerts(): void
    {
        config([
            'ppob.alerts.enabled' => true,
            'ppob.alerts.channel' => 'telegram',
            'ppob.alerts.cooldown_seconds' => 300,
            'ppob.alerts.telegram.bot_token' => 'mock-token',
            'ppob.alerts.telegram.chat_id' => 'mock-chat-id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $service = new OperationalAlertService();

        // Panggilan pertama: berhasil dikirim
        $firstCall = $service->notifyCircuitBreakerTripped('digiflazz', 5);
        $this->assertTrue($firstCall);

        // Panggilan kedua dalam cooldown: harus di-throttle (return false tanpa kirim HTTP)
        $secondCall = $service->notifyCircuitBreakerTripped('digiflazz', 5);
        $this->assertFalse($secondCall);

        // Hanya 1 HTTP request yang dikirim
        Http::assertSentCount(1);
    }

    public function test_circuit_breaker_tripping_triggers_operational_alert(): void
    {
        config([
            'ppob.alerts.enabled' => true,
            'ppob.alerts.channel' => 'telegram',
            'ppob.alerts.cooldown_seconds' => 300,
            'ppob.alerts.telegram.bot_token' => 'mock-token',
            'ppob.alerts.telegram.chat_id' => 'mock-chat-id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $cb = app(CircuitBreakerService::class);
        $cb->trip('telkomsel');

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'TELKOMSEL')
                && str_contains($request['text'], 'CIRCUIT BREAKER TRIPPED');
        });
    }

    public function test_circuit_breaker_recovery_triggers_restoration_alert(): void
    {
        config([
            'ppob.alerts.enabled' => true,
            'ppob.alerts.channel' => 'telegram',
            'ppob.alerts.cooldown_seconds' => 300,
            'ppob.alerts.telegram.bot_token' => 'mock-token',
            'ppob.alerts.telegram.chat_id' => 'mock-chat-id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $cb = app(CircuitBreakerService::class);
        // Buka circuit
        $cb->trip('indosat');

        // Supplier kembali berhasil
        $cb->recordSuccess('indosat');

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'INDOSAT')
                && str_contains($request['text'], 'CIRCUIT BREAKER RESTORED');
        });
    }

    public function test_routing_exhausted_triggers_operational_alert(): void
    {
        config([
            'ppob.alerts.enabled' => true,
            'ppob.alerts.channel' => 'telegram',
            'ppob.alerts.cooldown_seconds' => 300,
            'ppob.alerts.telegram.bot_token' => 'mock-token',
            'ppob.alerts.telegram.chat_id' => 'mock-chat-id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $category = ProductCategory::create(['name' => 'Data', 'code' => 'DATA', 'is_active' => true]);
        $provider = Provider::create(['name' => 'Digiflazz', 'code' => 'DIGIFLAZZ', 'driver' => 'DigiflazzDriver', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'sku_code' => 'EXHAUST_SKU',
            'name' => 'Paket Data 50GB',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 10000000,
            'is_active' => true,
            'allow_failover' => false, // Failover dimatikan
        ]);

        ProductSupplierRoute::create([
            'product_id' => $product->id,
            'provider_id' => $provider->id,
            'priority' => 1,
            'cost_cents' => 10000000,
            'is_active' => true,
        ]);

        // Trip provider circuit breaker
        app(CircuitBreakerService::class)->trip('DIGIFLAZZ');

        $router = app(SupplierRoutingService::class);

        $this->expectException(BusinessException::class);

        try {
            $router->resolve($product);
        } finally {
            Http::assertSent(function ($request) {
                return str_contains($request['text'], 'ROUTING EXHAUSTED')
                    && str_contains($request['text'], 'EXHAUST_SKU');
            });
        }
    }

    public function test_artisan_alert_test_command_runs_successfully(): void
    {
        $this->artisan('alert:test', ['--channel' => 'log'])
            ->expectsOutputToContain('Kanal Aktif: log')
            ->expectsOutputToContain('Berhasil!')
            ->assertSuccessful();
    }
}
