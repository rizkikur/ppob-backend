<?php

namespace Tests\Unit;

use App\Domain\Ppob\DTOs\ResolvedRoute;
use App\Domain\Product\Models\Provider;
use PHPUnit\Framework\TestCase;

class ResolvedRouteTest extends TestCase
{
    public function test_resolved_route_encapsulates_provider_and_original_provider_correctly(): void
    {
        $providerA = new Provider();
        $providerA->id = 10;
        $providerA->code = 'telkomsel_a';

        $providerB = new Provider();
        $providerB->id = 20;
        $providerB->code = 'telkomsel_b';

        $route = new ResolvedRoute(
            provider: $providerB,
            originalProvider: $providerA,
            isFailover: true,
            queueName: 'supplier_backup'
        );

        $this->assertEquals(20, $route->getProviderId());
        $this->assertEquals(10, $route->getOriginalProviderId());
        $this->assertTrue($route->isFailover);
        $this->assertEquals('supplier_backup', $route->queueName);
        $this->assertSame($providerB, $route->provider);
        $this->assertSame($providerA, $route->originalProvider);
    }

    public function test_resolved_route_without_failover(): void
    {
        $provider = new Provider();
        $provider->id = 15;
        $provider->code = 'pln';

        $route = new ResolvedRoute(
            provider: $provider,
            originalProvider: $provider,
            isFailover: false,
            queueName: 'supplier_pln'
        );

        $this->assertEquals(15, $route->getProviderId());
        $this->assertEquals(15, $route->getOriginalProviderId());
        $this->assertFalse($route->isFailover);
        $this->assertEquals('supplier_pln', $route->queueName);
    }
}
