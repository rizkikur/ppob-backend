<?php

namespace Tests\Feature\Ppob;

use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerRoutingRule;
use App\Domain\Ppob\DTOs\ResolvedRoute;
use App\Domain\Ppob\Drivers\TelkomselDriver;
use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Ppob\Services\SupplierRoutingService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Jobs\CheckTransactionStatusJob;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RoutingGuardsAndAutoFreezeTest extends TestCase
{
    use RefreshDatabase;

    private CircuitBreakerService $circuitBreaker;
    private SupplierRoutingService $routingService;
    private ProductCategory $category;
    private Provider $providerMain;
    private Provider $providerBackup;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->circuitBreaker = new CircuitBreakerService(failureThreshold: 3, cooldownSeconds: 30);
        $this->routingService = new SupplierRoutingService($this->circuitBreaker);

        $this->category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->providerMain = Provider::create([
            'name' => 'Telkomsel Supplier A',
            'code' => 'telkomsel_a',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
            'config' => ['supports_callback' => true, 'supports_check_status' => true],
        ]);

        $this->providerBackup = Provider::create([
            'name' => 'Telkomsel Supplier Backup',
            'code' => 'telkomsel_backup',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_backup',
            'is_active' => true,
            'config' => ['supports_callback' => false, 'supports_check_status' => true],
        ]);

        $this->product = Product::create([
            'provider_id' => $this->providerMain->id,
            'category_id' => $this->category->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000, // Rp 10.000
            'admin_fee_cents' => 0,
            'is_active' => true,
            'allow_failover' => true,
        ]);

        ProductSupplierRoute::create([
            'product_id' => $this->product->id,
            'provider_id' => $this->providerMain->id,
            'priority' => 1,
            'cost_cents' => 1000000,
            'is_active' => true,
        ]);

        ProductSupplierRoute::create([
            'product_id' => $this->product->id,
            'provider_id' => $this->providerBackup->id,
            'priority' => 2,
            'cost_cents' => 1010000, // Rp 10.100
            'is_active' => true,
        ]);
    }

    /** 1. Test opsi toggle per produk: jika allow_failover = false, failover ditolak */
    public function test_rejects_failover_when_product_allow_failover_is_disabled(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');
        $this->product->update(['allow_failover' => false]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Provider Telkomsel Supplier A sedang tidak tersedia');

        $this->routingService->resolve($this->product);
    }

    /** 2. Test guard anti-rugi: jika modal supplier backup > sell price, failover ditolak */
    public function test_anti_negative_margin_guard_skips_backup_with_cost_greater_than_sell_price(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');

        // Modal backup = Rp 10.100. Jika sellPrice kita Rp 10.050, failover harus ditolak agar garugi!
        $sellPrice = Money::fromCents(1005000);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Provider Telkomsel Supplier A sedang tidak tersedia');

        $this->routingService->resolve($this->product, null, $sellPrice);
    }

    /** 2b. Test guard anti-rugi: jika modal supplier backup <= sell price, failover diperbolehkan */
    public function test_anti_negative_margin_guard_allows_backup_when_margin_positive(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');

        // Modal backup = Rp 10.100. Jika sellPrice kita Rp 10.500, failover diizinkan
        $sellPrice = Money::fromCents(1050000);

        $resolved = $this->routingService->resolve($this->product, null, $sellPrice);
        $this->assertTrue($resolved->isFailover);
        $this->assertEquals($this->providerBackup->id, $resolved->getProviderId());
    }

    /** 3. Test kenaikan harga modal saat hit upstream: transaksi tetap sukses, tapi produk auto-update & auto-freeze */
    public function test_upstream_price_hike_auto_updates_and_freezes_product(): void
    {
        $tier = UserTier::create(['name' => 'Regular', 'label' => 'Regular', 'level' => 1, 'discount_percentage' => 0]);
        $user = User::create([
            'name' => 'Budi',
            'phone' => '081234567899',
            'user_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'balance_cents' => 5000000,
        ]);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567899',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1050000, // Jual Rp 10.500
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'idemp-hike-test',
            'supplier_id' => $this->providerMain->id,
        ]);

        // Mock upstream returning higher price: Rp 10.800 (modal naik melebihi harga jual)
        TelkomselDriver::setMockPay([
            'status' => 'success',
            'provider_ref' => 'TSEL-HIKE-123',
            'actual_price_cents' => 1080000,
            'raw' => [
                'provider' => 'Telkomsel',
                'price' => 10800,
                'message' => 'Pengisian berhasil dengan harga baru',
            ],
        ]);

        $job = new ProcessTransactionJob($tx);
        $job->handle(app(PpobService::class), app(TransactionService::class));

        // Transaksi ini tetap sukses (rugi 1x saja)
        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $tx->status);

        // Produk otomatis di-freeze (is_active = false) dan base_price diupdate ke Rp 10.800
        $this->product->refresh();
        $this->assertEquals(1080000, $this->product->base_price_cents->toCents());
        $this->assertFalse($this->product->is_active);

        TelkomselDriver::setMockPay(null);
    }

    /** 4. Test polling vs callback: supplier dengan callback tidak dispatch auto-polling */
    public function test_provider_with_callback_does_not_dispatch_auto_polling(): void
    {
        Queue::fake();

        $tier = UserTier::create(['name' => 'Tier2', 'label' => 'Tier2', 'level' => 1, 'discount_percentage' => 0]);
        $user = User::create([
            'name' => 'Andi',
            'phone' => '081234567888',
            'user_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567888',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1050000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'idemp-cb-test',
            'supplier_id' => $this->providerMain->id, // providerMain supports_callback = true
        ]);

        TelkomselDriver::setMockPay([
            'status' => 'pending',
            'provider_ref' => 'TSEL-ASYNC-1',
        ]);

        $job = new ProcessTransactionJob($tx);
        $job->handle(app(PpobService::class), app(TransactionService::class));

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_PROCESSING, $tx->status);

        // Tidak dispatch CheckTransactionStatusJob karena supplier punya callback
        Queue::assertNotPushed(CheckTransactionStatusJob::class);

        TelkomselDriver::setMockPay(null);
    }

    /** 5. Test polling vs callback: supplier tanpa callback dispatch polling dengan delay 2s */
    public function test_provider_without_callback_dispatches_polling_at_2_seconds(): void
    {
        Queue::fake();

        $tier = UserTier::create(['name' => 'Tier3', 'label' => 'Tier3', 'level' => 1, 'discount_percentage' => 0]);
        $user = User::create([
            'name' => 'Siti',
            'phone' => '081234567877',
            'user_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567877',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1050000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'idemp-nocb-test',
            'supplier_id' => $this->providerBackup->id, // providerBackup supports_callback = false
        ]);

        TelkomselDriver::setMockPay([
            'status' => 'pending',
            'provider_ref' => 'TSEL-NOCB-1',
        ]);

        $job = new ProcessTransactionJob($tx);
        $job->handle(app(PpobService::class), app(TransactionService::class));

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_PROCESSING, $tx->status);

        // Dispatch CheckTransactionStatusJob dengan delay awal 2 detik
        Queue::assertPushed(CheckTransactionStatusJob::class, function (CheckTransactionStatusJob $j) {
            return $j->delay !== null;
        });

        TelkomselDriver::setMockPay(null);
    }

    /** 6. Test Admin Partner Routing Rules Store & Destroy */
    public function test_admin_can_store_and_destroy_partner_routing_rules(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $partner = Partner::create([
            'name' => 'Mitra B2B Hub',
            'api_key' => 'pk_live_hub123',
            'secret' => 'sk_live_hub123',
            'is_active' => true,
        ]);

        // Store rule
        $response = $this->actingAs($admin, 'admin')->post(route('admin.partners.routing_rules.store', $partner), [
            'category_code' => 'pulsa',
            'preferred_provider_id' => $this->providerMain->id,
            'allow_failover' => true,
            'failover_policy' => PartnerRoutingRule::POLICY_SAME_CATEGORY,
        ]);

        $response->assertRedirect(route('admin.partners.show', $partner));
        $this->assertDatabaseHas('partner_routing_rules', [
            'partner_id' => $partner->id,
            'category_code' => 'pulsa',
            'preferred_provider_id' => $this->providerMain->id,
            'allow_failover' => 1,
            'failover_policy' => 'same_category',
        ]);

        $rule = PartnerRoutingRule::where('partner_id', $partner->id)->firstOrFail();

        // Destroy rule
        $deleteResponse = $this->actingAs($admin, 'admin')->delete(route('admin.partners.routing_rules.destroy', [$partner, $rule]));
        $deleteResponse->assertRedirect(route('admin.partners.show', $partner));
        $this->assertDatabaseMissing('partner_routing_rules', ['id' => $rule->id]);
    }
}
