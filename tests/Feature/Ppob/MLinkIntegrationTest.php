<?php

namespace Tests\Feature\Ppob;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProviderManagementService;
use App\Domain\Ppob\Drivers\MLinkGatewayDriver;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Ppob\Services\ProviderErrorMappingService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Models\ProviderErrorMapping;
use App\Domain\Product\Models\ProviderProduct;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\User\Models\User;
use Database\Seeders\MLinkProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MLinkIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MLinkGatewayDriver::enableSimulation(true);
    }

    public function test_mlink_provider_and_all_rc_mappings_seeded_properly(): void
    {
        $this->seed(MLinkProviderSeeder::class);

        $provider = Provider::where('code', 'mlink')->first();
        $this->assertNotNull($provider);
        $this->assertSame('M-Link Gateway', $provider->name);
        $this->assertSame('MLinkGatewayDriver', $provider->driver);
        $this->assertSame('mlink', $provider->callback_slug);
        $this->assertSame('supplier_mlink', $provider->queue_name);
        $this->assertTrue($provider->supports_balance_api);
        $this->assertTrue($provider->is_active);

        // Pastikan seluruh 28 Response Code (RC) M-Link terdaftar di provider_error_mappings
        $expectedRcs = [
            '000', '030', '003', '005', '006', '007', '008', '009',
            '011', '012', '013', '014', '015', '018', '019', '021',
            '022', '023', '024', '025', '026', '027', '031', '033',
            '034', '035', '036', '037',
        ];

        $this->assertSame(28, ProviderErrorMapping::where('provider_id', $provider->id)->count());

        foreach ($expectedRcs as $rc) {
            $this->assertDatabaseHas('provider_error_mappings', [
                'provider_id' => $provider->id,
                'upstream_code' => $rc,
            ]);
        }
    }

    public function test_error_mapping_service_resolves_all_mlink_response_codes(): void
    {
        $this->seed(MLinkProviderSeeder::class);
        $provider = Provider::where('code', 'mlink')->firstOrFail();

        /** @var ProviderErrorMappingService $service */
        $service = app(ProviderErrorMappingService::class);

        // 1. Sukses
        $res000 = $service->resolve($provider, '000');
        $this->assertSame(ProviderErrorMappingService::CODE_SUCCESS, $res000['internal_code']);
        $this->assertFalse($res000['is_retryable']);

        // 2. Pending (Biller sedang proses)
        $res030 = $service->resolve($provider, '030');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_SUSPECT_PENDING, $res030['internal_code']);
        $this->assertTrue($res030['is_retryable']);

        // 3. Timeout
        $res003 = $service->resolve($provider, '003');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_TIMEOUT, $res003['internal_code']);
        $this->assertTrue($res003['is_retryable']);

        // 4. Nomor salah (007)
        $res007 = $service->resolve($provider, '007');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_DEST_INVALID, $res007['internal_code']);
        $this->assertFalse($res007['is_retryable']);

        // 5. Gangguan biller (006)
        $res006 = $service->resolve($provider, '006');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_PROVIDER_DOWN, $res006['internal_code']);
        $this->assertTrue($res006['is_retryable']);

        // 6. Stok kuota habis (015)
        $res015 = $service->resolve($provider, '015');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_OUT_OF_STOCK, $res015['internal_code']);
        $this->assertTrue($res015['is_retryable']);

        // 7. Saldo deposit supplier tidak cukup (018)
        $res018 = $service->resolve($provider, '018');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_INSUFFICIENT_BALANCE, $res018['internal_code']);
        $this->assertTrue($res018['is_retryable']);

        // 8. Limit harian tercapai (027)
        $res027 = $service->resolve($provider, '027');
        $this->assertSame(ProviderErrorMappingService::CODE_ERR_LIMIT_EXCEEDED, $res027['internal_code']);
        $this->assertFalse($res027['is_retryable']);
    }

    public function test_sync_upstream_products_fetches_and_persists_catalog_to_database(): void
    {
        $this->seed(MLinkProviderSeeder::class);
        $provider = Provider::where('code', 'mlink')->firstOrFail();

        $admin = Admin::create([
            'name' => 'Super Administrator',
            'email' => 'super_mlink@example.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        /** @var AdminProviderManagementService $adminService */
        $adminService = app(AdminProviderManagementService::class);

        $result = $adminService->syncUpstreamProducts($provider, $admin);

        $this->assertGreaterThan(0, $result['synced']);

        // Pastikan produk upstream tersimpan di database
        $this->assertDatabaseHas('provider_products', [
            'provider_id' => $provider->id,
            'supplier_sku' => 'TS10L3',
            'category_name' => 'Telkomsel',
            'cost_cents' => 1010000,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('provider_products', [
            'provider_id' => $provider->id,
            'supplier_sku' => 'DNA10',
            'category_name' => 'DANA',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('provider_products', [
            'provider_id' => $provider->id,
            'supplier_sku' => 'PLN20',
            'category_name' => 'PLN',
            'status' => 'active',
        ]);

        $p = ProviderProduct::where('provider_id', $provider->id)->where('supplier_sku', 'TS10L3')->first();
        $this->assertNotNull($p->last_synced_at);
    }

    public function test_check_provider_balance_via_admin_service(): void
    {
        $this->seed(MLinkProviderSeeder::class);
        $provider = Provider::where('code', 'mlink')->firstOrFail();

        /** @var AdminProviderManagementService $adminService */
        $adminService = app(AdminProviderManagementService::class);

        $balanceRes = $adminService->checkProviderBalance($provider);

        $this->assertTrue($balanceRes['success']);
        $this->assertSame(50000100, $balanceRes['balance_cents']);
        $this->assertStringContainsString('500.001', $balanceRes['balance_formatted']);
    }

    public function test_ppob_service_resolves_mlink_driver(): void
    {
        $this->seed(MLinkProviderSeeder::class);

        /** @var PpobService $ppobService */
        $ppobService = app(PpobService::class);

        $driver = $ppobService->resolveDriverByCode('mlink');
        $this->assertInstanceOf(MLinkGatewayDriver::class, $driver);

        $driver2 = $ppobService->resolveDriverByCode('mlink_gateway');
        $this->assertInstanceOf(MLinkGatewayDriver::class, $driver2);
    }

    public function test_webhook_callback_updates_pending_transaction_to_success(): void
    {
        $this->seed(MLinkProviderSeeder::class);
        $provider = Provider::where('code', 'mlink')->firstOrFail();

        $tier = \App\Domain\Auth\Models\UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'End User']
        );

        $category = ProductCategory::firstOrCreate(
            ['code' => 'pulsa'],
            ['name' => 'Pulsa']
        );

        $product = Product::firstOrCreate(
            ['sku_code' => 'TS10L3'],
            [
                'name' => 'Telkomsel 10K',
                'category_id' => $category->id,
                'provider_id' => $provider->id,
                'base_price_cents' => 1010000,
                'product_type' => 'prepaid',
                'is_active' => true,
            ]
        );

        $user = \App\Domain\Auth\Models\User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Test MLink User',
            'phone' => '081380312000',
            'email' => 'mlink_user@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Buat transaksi yang sedang processing/pending
        $tx = Transaction::create([
            'idempotency_key' => 'TRX-MLINK-CALLBACK-001',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'customer_number' => '081380312000',
            'amount_cents' => 1050000,
            'sell_price_cents' => 1050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-WAITING-CALLBACK-001',
            'supplier_id' => $provider->id,
        ]);

        $callbackPayload = [
            'header' => [
                'req_id' => 'TRX-MLINK-CALLBACK-001',
                'ref_id' => 'REF-FINAL-12345',
            ],
            'body' => [
                'product_id' => 'TS10L3',
                'cust_id' => '081380312000',
                'status' => 'SUCCESS',
                'rc' => '000',
                'description' => 'Transaction Success',
                'serial_no' => '0041000180894618',
                'message' => 'TRX SUKSES via Webhook',
            ],
        ];

        $response = $this->postJson('/api/callback/mlink', $callbackPayload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verifikasi transaksi terupdate jadi SUCCESS dan SN tercatat
        $freshTx = $tx->fresh();
        $this->assertSame(Transaction::STATUS_SUCCESS, $freshTx->status);
        $this->assertSame('0041000180894618', $freshTx->provider_response['serial_number']);
    }
}
