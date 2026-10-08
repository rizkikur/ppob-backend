<?php

namespace Tests\Feature\Ppob;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerRoutingRule;
use App\Domain\Ppob\DTOs\ResolvedRoute;
use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Ppob\Services\SupplierRoutingService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupplierFailoverRoutingTest extends TestCase
{
    use RefreshDatabase;

    private CircuitBreakerService $circuitBreaker;

    private SupplierRoutingService $routingService;

    private ProductCategory $categoryPulsa;

    private Provider $providerTelkomsel;

    private Provider $providerBackup;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->circuitBreaker = new CircuitBreakerService(failureThreshold: 3, cooldownSeconds: 30);
        $this->routingService = new SupplierRoutingService($this->circuitBreaker);

        $this->categoryPulsa = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'pulsa',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->providerTelkomsel = Provider::create([
            'name' => 'Telkomsel Supplier A',
            'code' => 'telkomsel_a',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
        ]);

        $this->providerBackup = Provider::create([
            'name' => 'Telkomsel Supplier Backup',
            'code' => 'telkomsel_backup',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_backup',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $this->providerTelkomsel->id,
            'category_id' => $this->categoryPulsa->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1020000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);

        // Pemetaan rute supplier untuk produk ini
        ProductSupplierRoute::create([
            'product_id' => $this->product->id,
            'provider_id' => $this->providerTelkomsel->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        ProductSupplierRoute::create([
            'product_id' => $this->product->id,
            'provider_id' => $this->providerBackup->id,
            'priority' => 2,
            'is_active' => true,
        ]);
    }

    public function test_resolves_to_preferred_supplier_when_circuit_breaker_is_available(): void
    {
        $resolved = $this->routingService->resolve($this->product);

        $this->assertInstanceOf(ResolvedRoute::class, $resolved);
        $this->assertEquals($this->providerTelkomsel->id, $resolved->getProviderId());
        $this->assertEquals($this->providerTelkomsel->id, $resolved->getOriginalProviderId());
        $this->assertFalse($resolved->isFailover);
        $this->assertEquals('supplier_telkomsel', $resolved->queueName);
    }

    public function test_failover_to_backup_supplier_when_preferred_supplier_circuit_is_open(): void
    {
        // Buka circuit breaker provider utama
        $this->circuitBreaker->forceOpen('telkomsel_a');

        $partner = Partner::create([
            'name' => 'Mitra Sukses',
            'api_key' => 'key_partner_test',
            'secret' => 'secret_partner_test',
            'is_active' => true,
        ]);

        // Aturan: boleh failover untuk kategori pulsa
        PartnerRoutingRule::create([
            'partner_id' => $partner->id,
            'category_code' => 'pulsa',
            'preferred_provider_id' => $this->providerTelkomsel->id,
            'allow_failover' => true,
            'failover_policy' => PartnerRoutingRule::POLICY_SAME_CATEGORY,
        ]);

        $resolved = $this->routingService->resolve($this->product, $partner);

        $this->assertEquals($this->providerBackup->id, $resolved->getProviderId());
        $this->assertEquals($this->providerTelkomsel->id, $resolved->getOriginalProviderId());
        $this->assertTrue($resolved->isFailover);
        $this->assertEquals('supplier_backup', $resolved->queueName);
    }

    public function test_throws_exception_when_supplier_down_and_partner_disallows_failover(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');

        $partner = Partner::create([
            'name' => 'Mitra Anti-Failover',
            'api_key' => 'key_strict',
            'secret' => 'secret_strict',
            'is_active' => true,
        ]);

        PartnerRoutingRule::create([
            'partner_id' => $partner->id,
            'category_code' => 'pulsa',
            'preferred_provider_id' => $this->providerTelkomsel->id,
            'allow_failover' => false,
            'failover_policy' => PartnerRoutingRule::POLICY_NONE,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Provider Telkomsel Supplier A sedang tidak tersedia');

        $this->routingService->resolve($this->product, $partner);
    }

    public function test_partner_specific_category_rule_overrides_global_rule(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');

        $partner = Partner::create([
            'name' => 'Mitra Hierarki',
            'api_key' => 'key_hierarki',
            'secret' => 'secret_hierarki',
            'is_active' => true,
        ]);

        // Rule global: tolak failover
        PartnerRoutingRule::create([
            'partner_id' => $partner->id,
            'category_code' => null,
            'allow_failover' => false,
            'failover_policy' => PartnerRoutingRule::POLICY_NONE,
        ]);

        // Rule spesifik pulsa: izinkan failover
        PartnerRoutingRule::create([
            'partner_id' => $partner->id,
            'category_code' => 'pulsa',
            'allow_failover' => true,
            'failover_policy' => PartnerRoutingRule::POLICY_SAME_CATEGORY,
        ]);

        $resolved = $this->routingService->resolve($this->product, $partner);

        $this->assertEquals($this->providerBackup->id, $resolved->getProviderId());
        $this->assertTrue($resolved->isFailover);
    }

    public function test_partner_without_rules_defaults_to_failover_false(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');

        $partner = Partner::create([
            'name' => 'Mitra Polos',
            'api_key' => 'key_polos',
            'secret' => 'secret_polos',
            'is_active' => true,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Provider Telkomsel Supplier A sedang tidak tersedia');

        $this->routingService->resolve($this->product, $partner);
    }

    public function test_b2c_user_transaction_creation_with_failover_routing(): void
    {
        Queue::fake();
        $this->circuitBreaker->forceOpen('telkomsel_a');

        $tier = UserTier::create(['name' => 'Regular', 'label' => 'Regular', 'level' => 1, 'discount_percentage' => 0]);
        $user = User::create([
            'name' => 'Budi Pengguna',
            'phone' => '081234567890',
            'user_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'balance_cents' => 10000000,
        ]);

        /** @var TransactionService $txService */
        $txService = app(TransactionService::class);
        $transaction = $txService->create($user, $this->product, '081234567890');

        $this->assertEquals(Transaction::STATUS_PENDING, $transaction->status);
        $this->assertEquals($this->providerBackup->id, $transaction->supplier_id);
        $this->assertEquals($this->providerTelkomsel->id, $transaction->original_supplier_id);
        $this->assertTrue($transaction->is_failover);

        // Job di-dispatch ke queue provider backup
        Queue::assertPushed(ProcessTransactionJob::class, function (ProcessTransactionJob $job) {
            return $job->queue === 'supplier_backup';
        });
    }

    public function test_partner_transaction_aborts_and_does_not_debit_wallet_when_failover_disabled(): void
    {
        Queue::fake();
        $this->circuitBreaker->forceOpen('telkomsel_a');

        $partner = Partner::create([
            'name' => 'Mitra B2B Strict',
            'api_key' => 'key_b2b_strict',
            'secret' => 'secret_b2b_strict',
            'is_active' => true,
        ]);

        $partnerUser = $partner->getOrCreateUser();
        $wallet = Wallet::where('user_id', $partnerUser->id)->first();
        $wallet->update(['balance_cents' => 5000000]);

        PartnerRoutingRule::create([
            'partner_id' => $partner->id,
            'category_code' => 'pulsa',
            'allow_failover' => false,
            'failover_policy' => PartnerRoutingRule::POLICY_NONE,
        ]);

        /** @var TransactionService $txService */
        $txService = app(TransactionService::class);

        try {
            $txService->createForPartner($partner, $this->product, '081234567890');
            $this->fail('Harus melempar exception provider unavailable');
        } catch (BusinessException $e) {
            $this->assertEquals('PROVIDER_UNAVAILABLE', $e->getErrorCode());
            $this->assertEquals(503, $e->getHttpStatus());
        }

        // Saldo tidak terpotong
        $wallet->refresh();
        $this->assertEquals(5000000, $wallet->balance_cents->toCents());
        $this->assertEquals(0, WalletMutation::count());
        $this->assertEquals(0, Transaction::count());
        Queue::assertNothingPushed();
    }

    public function test_throws_when_all_suppliers_are_down(): void
    {
        $this->circuitBreaker->forceOpen('telkomsel_a');
        $this->circuitBreaker->forceOpen('telkomsel_backup');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Provider Telkomsel Supplier A sedang tidak tersedia');

        $this->routingService->resolve($this->product);
    }
}
