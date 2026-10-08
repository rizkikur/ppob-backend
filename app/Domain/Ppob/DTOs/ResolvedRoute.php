<?php

namespace App\Domain\Ppob\DTOs;

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
        public readonly string $queueName
    ) {}

    public function getProviderId(): int
    {
        return $this->provider->id;
    }

    public function getOriginalProviderId(): int
    {
        return $this->originalProvider->id;
    }
}
