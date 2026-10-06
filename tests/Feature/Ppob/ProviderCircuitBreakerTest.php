<?php

namespace Tests\Feature\Ppob;

use App\Domain\Ppob\Drivers\PlnDriver;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    private CircuitBreakerService $circuitBreaker;

    private PpobService $ppobService;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->circuitBreaker = new CircuitBreakerService(failureThreshold: 3, cooldownSeconds: 10);
        $this->ppobService = new PpobService($this->circuitBreaker);

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
            'sku_code' => 'PLN-TEST',
            'name' => 'PLN Test Product',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 2000000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        PlnDriver::setMockPay(null);
        parent::tearDown();
    }

    public function test_circuit_starts_as_closed_and_available(): void
    {
        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->circuitBreaker->getState('pln'));
        $this->assertTrue($this->circuitBreaker->isAvailable('pln'));
    }

    public function test_circuit_trips_to_open_after_reaching_failure_threshold(): void
    {
        $this->circuitBreaker->recordFailure('pln');
        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->circuitBreaker->getState('pln'));

        $this->circuitBreaker->recordFailure('pln');
        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->circuitBreaker->getState('pln'));

        // Kegagalan ke-3 (mencapai threshold 3)
        $this->circuitBreaker->recordFailure('pln');
        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->circuitBreaker->getState('pln'));
        $this->assertFalse($this->circuitBreaker->isAvailable('pln'));
    }

    public function test_when_circuit_open_request_is_rejected_fast_with_provider_unavailable(): void
    {
        // Paksa status circuit menjadi OPEN
        $this->circuitBreaker->trip('pln');

        $this->expectException(BusinessException::class);

        try {
            $this->ppobService->pay(
                $this->product,
                '1234567890',
                Money::fromCents(2000000),
                'REF-TEST-01'
            );
        } catch (BusinessException $e) {
            $this->assertEquals('PROVIDER_UNAVAILABLE', $e->getErrorCode());
            $this->assertEquals(503, $e->getHttpStatus());
            throw $e;
        }
    }

    public function test_circuit_breaker_resets_on_success(): void
    {
        $this->circuitBreaker->recordFailure('pln');
        $this->circuitBreaker->recordFailure('pln');

        // Request berhasil
        $this->circuitBreaker->recordSuccess('pln');

        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->circuitBreaker->getState('pln'));
        $this->assertTrue($this->circuitBreaker->isAvailable('pln'));
    }
}
