<?php

namespace App\Domain\Wallet\Drivers;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Models\TopupRequest;

/** Driver Xendit untuk payment gateway top-up. TODO: Implementasi di Phase 3. */
class XenditGatewayDriver implements PaymentGatewayInterface
{
    public function createPayment(TopupRequest $topup, Money $amount): array
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public function parseWebhookStatus(array $payload): string
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public function parseWebhookRef(array $payload): string
    {
        throw new \RuntimeException('Not implemented yet');
    }
}
