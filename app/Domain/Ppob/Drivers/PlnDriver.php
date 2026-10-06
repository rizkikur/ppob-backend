<?php

namespace App\Domain\Ppob\Drivers;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Driver PLN — Token Listrik (prepaid) & Tagihan Listrik (postpaid).
 */
class PlnDriver implements PpobProviderInterface
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

        $ref = 'PLN-INQ-'.time().'-'.substr(md5($customerNumber), 0, 6);
        $amountCents = 25000000; // Rp 250.000,00 nominal tagihan listrik standar

        return [
            'ref' => $ref,
            'amount_cents' => $amountCents,
            'customer_name' => 'PELANGGAN PLN / '.$customerNumber,
            'raw' => [
                'meter_number' => $customerNumber,
                'subscriber_name' => 'PELANGGAN PLN / '.$customerNumber,
                'tariff_power' => 'R1 / 1300 VA',
                'stand_meter' => '012345-012567',
                'bill_period' => date('Y-m'),
                'amount_cents' => $amountCents,
            ],
        ];
    }

    public function pay(string $customerNumber, string $productCode, Money $amount, string $transactionRef): array
    {
        return [
            'status' => 'success',
            'provider_ref' => 'PLN-PAY-'.time(),
            'raw' => ['message' => 'Pembayaran PLN berhasil'],
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
