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

    public static function setMockInquiry(?array $result): void
    {
        self::$mockInquiryResult = $result;
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
        return [
            'status' => 'success',
            'provider_ref' => 'PDAM-PAY-'.time(),
            'raw' => ['message' => 'Pembayaran PDAM berhasil'],
        ];
    }

    public function checkStatus(string $providerRef): array
    {
        return [
            'status' => 'success',
            'provider_ref' => $providerRef,
            'raw' => ['status' => 'success'],
        ];
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        return true;
    }
}
