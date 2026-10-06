<?php

namespace App\Domain\Wallet\Contracts;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\TopupRequest;

/**
 * Interface untuk driver payment gateway.
 *
 * Implementasi:
 * - MidtransGatewayDriver
 * - XenditGatewayDriver
 *
 * Driver aktif dipilih berdasarkan request field 'payment_gateway'.
 */
interface PaymentGatewayInterface
{
    /**
     * Buat sesi pembayaran di gateway dan kembalikan URL pembayaran.
     *
     * @return array{payment_url: string, gateway_ref: string, payload: array}
     */
    public function createPayment(TopupRequest $topup, Money $amount): array;

    /**
     * Verifikasi payload webhook dari gateway.
     *
     * @param  array  $payload  Payload mentah dari webhook
     * @param  string  $signature  Signature dari header webhook
     * @return bool True jika signature valid
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Parse status pembayaran dari payload webhook.
     *
     * @return string 'paid' | 'failed' | 'expired' | 'pending'
     */
    public function parseWebhookStatus(array $payload): string;

    /**
     * Ambil gateway_ref dari payload webhook.
     */
    public function parseWebhookRef(array $payload): string;
}
