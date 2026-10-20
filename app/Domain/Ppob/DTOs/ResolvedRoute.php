<?php

namespace App\Domain\Ppob\DTOs;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Provider;

/**
 * DTO hasil resolusi rute supplier (ADR-004 & ADR-005).
 */
class ResolvedRoute
{
    public function __construct(
        public readonly Provider $provider,
        public readonly Provider $originalProvider,
        public readonly bool $isFailover,
        public readonly string $queueName,
        public readonly ?string $supplierSku = null,
        public readonly ?int $costCents = null
    ) {}

    public function getProviderId(): int
    {
        return $this->provider->id;
    }

    public function getOriginalProviderId(): int
    {
        return $this->originalProvider->id;
    }

    public function getEffectiveSku(Product|string $product): string
    {
        if (! empty($this->supplierSku)) {
            return $this->supplierSku;
        }

        return $product instanceof Product ? $product->sku_code : (string) $product;
    }
}
