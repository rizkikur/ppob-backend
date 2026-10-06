<?php

namespace App\Domain\Wallet\Drivers;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Models\TopupRequest;

/**
 * Fake driver untuk payment gateway pada saat testing.
 */
class FakeGatewayDriver implements PaymentGatewayInterface
{
    private string $statusToReturn = 'paid';

    private bool $verifyResult = true;

    /**
     * {@inheritDoc}
     */
    public function createPayment(TopupRequest $topup, Money $amount): array
    {
        $ref = 'FAKE-TOPUP-'.$topup->id.'-'.time();
        $url = 'https://app.fake-gateway.test/pay/'.$ref;

        return [
            'payment_url' => $url,
            'gateway_ref' => $ref,
            'payload' => [
                'order_id' => $ref,
                'amount' => $amount->toCents(),
                'status' => 'pending',
                'payment_url' => $url,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function verifyWebhook(array $payload, string $signature): bool
    {
        return $this->verifyResult;
    }

    /**
     * {@inheritDoc}
     */
    public function parseWebhookStatus(array $payload): string
    {
        return $payload['status'] ?? $this->statusToReturn;
    }

    /**
     * {@inheritDoc}
     */
    public function parseWebhookRef(array $payload): string
    {
        return $payload['order_id'] ?? $payload['gateway_ref'] ?? '';
    }

    public function setVerifyResult(bool $result): void
    {
        $this->verifyResult = $result;
    }

    public function setStatusToReturn(string $status): void
    {
        $this->statusToReturn = $status;
    }
}
