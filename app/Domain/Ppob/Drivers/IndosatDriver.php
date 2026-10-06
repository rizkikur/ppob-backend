<?php

namespace App\Domain\Ppob\Drivers;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Shared\ValueObjects\Money;

/** Driver Indosat — Pulsa & Paket Data. TODO: Implementasi di Phase 7. */
class IndosatDriver implements PpobProviderInterface
{
    public function inquiry(string $customerNumber, string $productCode): array
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public function pay(string $customerNumber, string $productCode, Money $amount, string $transactionRef): array
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public function checkStatus(string $providerRef): array
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        throw new \RuntimeException('Not implemented yet');
    }
}
