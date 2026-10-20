<?php

namespace Tests\Feature\Admin\Providers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;
use App\Domain\Product\Models\ProviderProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProviderCustomProductAndErrorMappingTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;
    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Test Supplier Gateway',
            'code' => 'test_supplier',
            'callback_slug' => 'test-supplier-hook',
            'driver' => 'App\Domain\Ppob\Drivers\MLinkGatewayDriver',
            'config' => [
                'base_url' => 'https://api.supplier.id',
                'username' => 'TEST01',
            ],
            'max_workers' => 5,
            'rate_limit_per_minute' => 60,
            'timeout_seconds' => 30,
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_store_upstream_product_manually_with_custom_urls(): void
    {
        $payload = [
            'supplier_sku' => 'DANA50K',
            'name' => 'DANA Saldo Rp 50.000',
            'category_name' => 'E-Wallet',
            'cost' => 49800,
            'status' => 'active',
            'inquiry_url' => '/v1/inquiry/ewallet',
            'payment_url' => '/v1/order/ewallet',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.providers.products.store', $this->provider), $payload);

        $response->assertRedirect(route('admin.providers.show', ['provider' => $this->provider, 'tab' => 'products']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('provider_products', [
            'provider_id' => $this->provider->id,
            'supplier_sku' => 'DANA50K',
            'name' => 'DANA Saldo Rp 50.000',
            'category_name' => 'E-Wallet',
            'cost_cents' => 4980000,
            'status' => 'active',
            'inquiry_url' => '/v1/inquiry/ewallet',
            'payment_url' => '/v1/order/ewallet',
        ]);
    }

    public function test_admin_can_update_upstream_product_and_custom_urls(): void
    {
        $product = ProviderProduct::create([
            'provider_id' => $this->provider->id,
            'supplier_sku' => 'PLN100',
            'name' => 'PLN Pascabayar',
            'category_name' => 'PLN',
            'cost_cents' => 10000000,
            'status' => 'active',
            'inquiry_url' => '/v1/inquiry/pln',
            'payment_url' => '/v1/payment/pln',
        ]);

        $updatePayload = [
            'supplier_sku' => 'PLN100_UPDATED',
            'name' => 'PLN Pascabayar Bulanan',
            'category_name' => 'Listrik PLN',
            'cost' => 100500,
            'status' => 'inactive',
            'inquiry_url' => 'https://pln-checker.supplier.id/inquiry',
            'payment_url' => 'https://pln-payment.supplier.id/pay',
        ];

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->put(route('admin.providers.products.update', ['provider' => $this->provider, 'product' => $product]), $updatePayload);

        $response->assertRedirect(route('admin.providers.show', ['provider' => $this->provider, 'tab' => 'products']))
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals('PLN100_UPDATED', $product->supplier_sku);
        $this->assertEquals('PLN Pascabayar Bulanan', $product->name);
        $this->assertEquals(10050000, $product->cost_cents);
        $this->assertEquals('inactive', $product->status);
        $this->assertEquals('https://pln-checker.supplier.id/inquiry', $product->inquiry_url);
        $this->assertEquals('https://pln-payment.supplier.id/pay', $product->payment_url);
    }

    public function test_admin_can_delete_upstream_product(): void
    {
        $product = ProviderProduct::create([
            'provider_id' => $this->provider->id,
            'supplier_sku' => 'DELETE_ME',
            'name' => 'To be deleted',
            'cost_cents' => 500000,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.providers.products.destroy', ['provider' => $this->provider, 'product' => $product]));

        $response->assertRedirect(route('admin.providers.show', ['provider' => $this->provider, 'tab' => 'products']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('provider_products', [
            'id' => $product->id,
        ]);
    }

    public function test_provider_product_effective_url_resolvers(): void
    {
        // 1. Relative paths
        $productRelative = new ProviderProduct([
            'provider_id' => $this->provider->id,
            'inquiry_url' => '/v1/check/ewallet',
            'payment_url' => '/v1/pay/ewallet',
        ]);
        $productRelative->setRelation('provider', $this->provider);

        $this->assertEquals('https://api.supplier.id/v1/check/ewallet', $productRelative->getEffectiveInquiryUrl());
        $this->assertEquals('https://api.supplier.id/v1/pay/ewallet', $productRelative->getEffectivePaymentUrl());

        // 2. Full URLs
        $productFull = new ProviderProduct([
            'provider_id' => $this->provider->id,
            'inquiry_url' => 'https://other-service.com/inquiry',
            'payment_url' => 'https://other-service.com/pay',
        ]);
        $productFull->setRelation('provider', $this->provider);

        $this->assertEquals('https://other-service.com/inquiry', $productFull->getEffectiveInquiryUrl());
        $this->assertEquals('https://other-service.com/pay', $productFull->getEffectivePaymentUrl());

        // 3. Empty URLs return null so caller/driver uses default endpoint
        $productEmpty = new ProviderProduct([
            'provider_id' => $this->provider->id,
            'inquiry_url' => null,
            'payment_url' => '',
        ]);
        $productEmpty->setRelation('provider', $this->provider);

        $this->assertNull($productEmpty->getEffectiveInquiryUrl());
        $this->assertNull($productEmpty->getEffectivePaymentUrl());
    }

    public function test_admin_can_access_central_error_mapping_dictionary(): void
    {
        ProviderErrorMapping::create([
            'provider_id' => $this->provider->id,
            'upstream_code' => '42',
            'internal_code' => 'ERR_OUT_OF_STOCK',
            'custom_message' => 'Produk sedang gangguan supplier',
            'is_retryable' => false,
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.error_mappings.index'));

        $response->assertOk()
            ->assertViewIs('admin.providers.error_mappings_index')
            ->assertSee('Kamus Response Code')
            ->assertSee('42')
            ->assertSee('ERR_OUT_OF_STOCK')
            ->assertSee('Produk sedang gangguan supplier');
    }

    public function test_admin_can_store_and_update_error_mapping_via_central_endpoints(): void
    {
        // Store
        $storePayload = [
            'provider_id' => $this->provider->id,
            'upstream_code' => '55',
            'internal_code' => 'ERR_INSUFFICIENT_BALANCE',
            'custom_message' => 'Saldo deposit supplier menipis',
            'is_retryable' => '1',
        ];

        $storeResponse = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.error_mappings.store'), $storePayload);

        $storeResponse->assertRedirect()
            ->assertSessionHas('success');

        $mapping = ProviderErrorMapping::where('provider_id', $this->provider->id)
            ->where('upstream_code', '55')
            ->first();

        $this->assertNotNull($mapping);
        $this->assertEquals('ERR_INSUFFICIENT_BALANCE', $mapping->internal_code);
        $this->assertTrue((bool)$mapping->is_retryable);

        // Update
        $updatePayload = [
            'upstream_code' => '55',
            'internal_code' => 'ERR_PROVIDER_DOWN',
            'custom_message' => 'Supplier mengalami kendala koneksi',
            'is_retryable' => '0',
        ];

        $updateResponse = $this->actingAs($this->superAdmin, 'admin')
            ->put(route('admin.error_mappings.update', $mapping), $updatePayload);

        $updateResponse->assertRedirect()
            ->assertSessionHas('success');

        $mapping->refresh();
        $this->assertEquals('ERR_PROVIDER_DOWN', $mapping->internal_code);
        $this->assertEquals('Supplier mengalami kendala koneksi', $mapping->custom_message);
        $this->assertFalse((bool)$mapping->is_retryable);

        // Destroy
        $deleteResponse = $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.error_mappings.destroy', $mapping));

        $deleteResponse->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('provider_error_mappings', [
            'id' => $mapping->id,
        ]);
    }

    public function test_admin_can_create_update_and_delete_system_response_code(): void
    {
        // 1. Create custom code
        $payload = [
            'rc' => '89',
            'slug' => 'ERR_SUSPECT_KYC',
            'name' => 'Akun Pengguna Belum KYC',
            'status_type' => 'FAILED',
            'default_message' => 'Akun pelanggan belum terverifikasi KYC.',
            'is_retryable' => '0',
        ];

        $createResponse = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.error_mappings.system_codes.store'), $payload);

        $createResponse->assertRedirect(route('admin.error_mappings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('system_response_codes', [
            'rc' => '89',
            'slug' => 'ERR_SUSPECT_KYC',
            'name' => 'Akun Pengguna Belum KYC',
            'is_system' => false,
        ]);

        $customCode = \App\Domain\Product\Models\SystemResponseCode::where('slug', 'ERR_SUSPECT_KYC')->first();
        $this->assertNotNull($customCode);

        // 2. Update custom code
        $updatePayload = [
            'rc' => '89',
            'slug' => 'ERR_SUSPECT_KYC',
            'name' => 'Akun Belum Verifikasi Identitas',
            'status_type' => 'FAILED',
            'default_message' => 'Silakan lakukan verifikasi KYC KTP terlebih dahulu.',
            'is_retryable' => '0',
        ];

        $updateResponse = $this->actingAs($this->superAdmin, 'admin')
            ->put(route('admin.error_mappings.system_codes.update', $customCode), $updatePayload);

        $updateResponse->assertRedirect(route('admin.error_mappings.index'))
            ->assertSessionHas('success');

        $customCode->refresh();
        $this->assertEquals('Akun Belum Verifikasi Identitas', $customCode->name);
        $this->assertEquals('Silakan lakukan verifikasi KYC KTP terlebih dahulu.', $customCode->default_message);

        // 3. Delete custom code
        $deleteResponse = $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.error_mappings.system_codes.destroy', $customCode));

        $deleteResponse->assertRedirect(route('admin.error_mappings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('system_response_codes', [
            'id' => $customCode->id,
        ]);
    }

    public function test_admin_cannot_delete_system_locked_response_code(): void
    {
        $systemCode = \App\Domain\Product\Models\SystemResponseCode::firstOrCreate(
            ['slug' => 'SUCCESS'],
            [
                'rc' => '00',
                'name' => 'Transaksi Berhasil',
                'status_type' => 'SUCCESS',
                'default_message' => 'Transaksi berhasil diproses.',
                'is_retryable' => false,
                'is_system' => true,
            ]
        );

        $deleteResponse = $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.error_mappings.system_codes.destroy', $systemCode));

        $deleteResponse->assertRedirect(route('admin.error_mappings.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('system_response_codes', [
            'id' => $systemCode->id,
            'slug' => 'SUCCESS',
        ]);
    }

    public function test_provider_error_mapping_service_caches_in_redis_and_invalidates_properly(): void
    {
        $service = app(\App\Domain\Ppob\Services\ProviderErrorMappingService::class);

        // Clear existing cache
        \App\Domain\Ppob\Services\ProviderErrorMappingService::clearAllCache();

        // 1. First call caches system definitions
        $definitions = \App\Domain\Ppob\Services\ProviderErrorMappingService::getStandardErrorDefinitions();
        $this->assertNotEmpty($definitions);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(\App\Domain\Ppob\Services\ProviderErrorMappingService::CACHE_KEY_SYSTEM_DEFINITIONS));

        // 2. Map an error for provider and resolve
        ProviderErrorMapping::create([
            'provider_id' => $this->provider->id,
            'upstream_code' => 'TEST_99',
            'internal_code' => 'ERR_GENERAL',
            'custom_message' => 'Gagal sistematis upstream',
            'is_retryable' => false,
        ]);

        // Resolving should cache the provider's mappings
        $resolved = $service->resolve($this->provider, 'TEST_99');
        $this->assertTrue($resolved['is_mapped']);
        $this->assertEquals('Gagal sistematis upstream', $resolved['message']);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(\App\Domain\Ppob\Services\ProviderErrorMappingService::CACHE_KEY_PROVIDER_PREFIX . $this->provider->id));

        // 3. Invalidation test
        \App\Domain\Ppob\Services\ProviderErrorMappingService::clearProviderCache($this->provider);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has(\App\Domain\Ppob\Services\ProviderErrorMappingService::CACHE_KEY_PROVIDER_PREFIX . $this->provider->id));

        \App\Domain\Ppob\Services\ProviderErrorMappingService::clearSystemCache();
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has(\App\Domain\Ppob\Services\ProviderErrorMappingService::CACHE_KEY_SYSTEM_DEFINITIONS));
    }
}
