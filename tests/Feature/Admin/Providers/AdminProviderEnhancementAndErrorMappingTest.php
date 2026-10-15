<?php

namespace Tests\Feature\Admin\Providers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Ppob\Services\ProviderErrorMappingService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;
use App\Domain\Product\Models\ProviderProduct;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProviderEnhancementAndErrorMappingTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;
    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'name' => 'Super Administrator',
            'email' => 'super@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Baseindo Gateway',
            'code' => 'baseindo',
            'callback_slug' => 'baseindo-hook',
            'driver' => 'App\Domain\Ppob\Providers\BaseindoProvider',
            'config' => [
                'base_url' => 'https://api.baseindo.com',
                'username' => 'BASE001',
                'supports_callback' => true,
                'supports_check_status' => true,
            ],
            'max_workers' => 10,
            'rate_limit_per_minute' => 120,
            'timeout_seconds' => 45,
            'priority' => 1,
            'supports_balance_api' => true,
            'balance_alert_threshold_cents' => 100000000,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_provider_detail_with_tabs_and_error_mappings(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.providers.show', $this->provider));

        $response->assertOk()
            ->assertViewIs('admin.providers.show')
            ->assertSee('Baseindo Gateway')
            ->assertSee('baseindo-hook')
            ->assertSee('/api/callback/baseindo-hook')
            ->assertSee('Kredensial', false)
            ->assertSee('Katalog Produk Upstream', false)
            ->assertSee('Mapping Kode Error', false);
    }

    public function test_admin_can_update_provider_settings_credentials_and_callback_slug(): void
    {
        $payload = [
            'name' => 'Baseindo Telekoma Utama',
            'callback_slug' => 'baseindo-v2',
            'driver' => 'App\Domain\Ppob\Providers\BaseindoProvider',
            'base_url' => 'https://api.baseindo-v2.com',
            'port' => 8443,
            'username' => 'MERCHANT99',
            'password' => 'newSecretPass123!',
            'api_key' => 'API-KEY-999',
            'secret_key' => 'HMAC-SECRET-888',
            'queue_name' => 'ppob-baseindo',
            'max_workers' => 15,
            'rate_limit_per_minute' => 200,
            'timeout_seconds' => 60,
            'priority' => 2,
            'supports_callback' => 1,
            'supports_check_status' => 1,
            'supports_balance_api' => 1,
            'balance_alert_threshold' => 2500000, // Rp 2.500.000
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->put(route('admin.providers.update', $this->provider), $payload);

        $response->assertRedirect(route('admin.providers.show', $this->provider))
            ->assertSessionHas('success');

        $this->provider->refresh();

        $this->assertSame('Baseindo Telekoma Utama', $this->provider->name);
        $this->assertSame('baseindo-v2', $this->provider->callback_slug);
        $this->assertSame(2, $this->provider->priority);
        $this->assertSame(15, $this->provider->max_workers);
        $this->assertSame(250000000, $this->provider->balance_alert_threshold_cents);
        $this->assertSame('https://api.baseindo-v2.com', $this->provider->config['base_url']);
        $this->assertSame('MERCHANT99', $this->provider->config['username']);
        $this->assertSame('newSecretPass123!', $this->provider->config['password']);
        $this->assertSame('HMAC-SECRET-888', $this->provider->config['secret_key']);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'provider.update_settings',
            'target_id' => $this->provider->id,
        ]);
    }

    public function test_admin_can_store_and_delete_error_mappings(): void
    {
        // 1. Create mapping
        $storePayload = [
            'upstream_code' => '42',
            'internal_code' => ProviderErrorMappingService::CODE_ERR_DEST_INVALID,
            'custom_message' => 'Nomor tujuan tidak valid atau telah diblokir',
            'is_retryable' => 0,
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.providers.error_mappings.store', $this->provider), $storePayload);

        $response->assertRedirect(route('admin.providers.show', ['provider' => $this->provider, 'tab' => 'errors']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('provider_error_mappings', [
            'provider_id' => $this->provider->id,
            'upstream_code' => '42',
            'internal_code' => ProviderErrorMappingService::CODE_ERR_DEST_INVALID,
            'custom_message' => 'Nomor tujuan tidak valid atau telah diblokir',
            'is_retryable' => false,
        ]);

        /** @var ProviderErrorMapping $mapping */
        $mapping = ProviderErrorMapping::where('provider_id', $this->provider->id)
            ->where('upstream_code', '42')
            ->firstOrFail();

        // 2. Delete mapping
        $deleteResponse = $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.providers.error_mappings.destroy', ['provider' => $this->provider, 'mapping' => $mapping]));

        $deleteResponse->assertRedirect(route('admin.providers.show', ['provider' => $this->provider, 'tab' => 'errors']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('provider_error_mappings', [
            'id' => $mapping->id,
        ]);
    }

    public function test_admin_can_sync_upstream_products(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.providers.sync_products', $this->provider));

        $response->assertRedirect(route('admin.providers.show', ['provider' => $this->provider, 'tab' => 'products']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('provider_products', [
            'provider_id' => $this->provider->id,
            'supplier_sku' => 'dna50',
        ]);

        $this->assertGreaterThan(0, ProviderProduct::where('provider_id', $this->provider->id)->count());
    }

    public function test_dynamic_callback_webhook_endpoint_identifies_provider(): void
    {
        // Provider callback_slug = 'baseindo-hook'
        $response = $this->postJson("/api/callback/{$this->provider->callback_slug}", [
            'event_id' => 'evt-test-12345',
            'transaction_id' => 999999,
            'status' => 'success',
        ], [
            'X-Signature' => 'dummy', // Will fail signature verification in generic handler, but prove endpoint reached provider
            'X-Timestamp' => now()->timestamp,
        ]);

        // Returns either 401 signature invalid or 200, but NOT 404
        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals('PROVIDER_NOT_FOUND', $response->json('error_code'));
    }

    public function test_dynamic_callback_webhook_returns_404_for_unknown_slug(): void
    {
        $response = $this->postJson('/api/callback/non-existent-supplier-xyz', [
            'event_id' => 'evt-999',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'PROVIDER_NOT_FOUND',
            ]);
    }

    public function test_error_mapping_service_resolves_mapped_code_accurately(): void
    {
        ProviderErrorMapping::create([
            'provider_id' => $this->provider->id,
            'upstream_code' => '55',
            'internal_code' => ProviderErrorMappingService::CODE_ERR_OUT_OF_STOCK,
            'custom_message' => 'Stok produk di supplier Baseindo sedang kosong',
            'is_retryable' => true,
        ]);

        $service = app(ProviderErrorMappingService::class);
        $result = $service->resolve($this->provider, '55');

        $this->assertSame(ProviderErrorMappingService::CODE_ERR_OUT_OF_STOCK, $result['internal_code']);
        $this->assertSame('Stok produk di supplier Baseindo sedang kosong', $result['message']);
        $this->assertTrue($result['is_retryable']);
        $this->assertTrue($result['is_mapped']);
    }

    public function test_admin_can_check_provider_balance(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.providers.check_balance', $this->provider));

        $response->assertRedirect()
            ->assertSessionHas('success');
    }
}
