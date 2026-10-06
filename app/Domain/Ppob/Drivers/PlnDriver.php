<?php

namespace App\Domain\Ppob\Drivers;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Shared\ValueObjects\Money;

/** Driver PLN — Token Listrik (prepaid) & Tagihan Listrik (postpaid). TODO: Implementasi di Phase 7. */
class PlnDriver implements PpobProviderInterface
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
