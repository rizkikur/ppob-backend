<?php

namespace App\Domain\Ppob\Drivers;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Driver PDAM — Tagihan Air.
 */
class PdamDriver implements PpobProviderInterface
{
    private static ?array $mockInquiryResult = null;

    private static ?array $mockPayResult = null;

    private static ?array $mockCheckStatusResult = null;

    public static function setMockInquiry(?array $result): void
    {
        self::$mockInquiryResult = $result;
    }

    public static function setMockPay(?array $result): void
    {
        self::$mockPayResult = $result;
    }

    public static function setMockCheckStatus(?array $result): void
    {
        self::$mockCheckStatusResult = $result;
    }

    public function inquiry(string $customerNumber, string $productCode): array
    {
        if (self::$mockInquiryResult !== null) {
            return self::$mockInquiryResult;
        }

        $ref = 'PDAM-INQ-'.time().'-'.substr(md5($customerNumber), 0, 6);
        $amountCents = 17500000; // Rp 175.000,00 tagihan PDAM

        return [
            'ref' => $ref,
            'amount_cents' => $amountCents,
            'customer_name' => 'PELANGGAN PDAM / '.$customerNumber,
            'raw' => [
                'customer_number' => $customerNumber,
                'customer_name' => 'PELANGGAN PDAM / '.$customerNumber,
                'meter_usage' => '24 m3',
                'bill_period' => date('Y-m'),
                'amount_cents' => $amountCents,
            ],
        ];
    }

    public function pay(string $customerNumber, string $productCode, Money $amount, string $transactionRef): array
    {
        if (self::$mockPayResult !== null) {
            return self::$mockPayResult;
        }

        return [
            'status' => 'success',
            'provider_ref' => 'PDAM-PAY-'.time(),
            'raw' => ['message' => 'Pembayaran PDAM berhasil'],
        ];
    }

    public function checkStatus(string $providerRef): array
    {
        if (self::$mockCheckStatusResult !== null) {
            return self::$mockCheckStatusResult;
        }

        return [
            'status' => 'success',
            'provider_ref' => $providerRef,
            'raw' => ['status' => 'success'],
        ];
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('ppob.providers.pdam.webhook_secret') ?: 'pdam_secret_key';
        $expected = hash_hmac('sha256', $rawBody, $secret);

        $cleanSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $cleanSignature);
    }
}
