<?php

namespace App\Domain\Ppob\Drivers;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Str;

/**
 * Driver Telkomsel — Pulsa & Paket Data (Prepaid).
 */
class TelkomselDriver implements PpobProviderInterface
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

        // Produk Telkomsel bersifat prepaid, tidak mendukung inquiry tagihan
        throw BusinessException::productTypeMismatch('postpaid', 'prepaid');
    }

    public function pay(string $customerNumber, string $productCode, Money $amount, string $transactionRef): array
    {
        if (self::$mockPayResult !== null) {
            return self::$mockPayResult;
        }

        $providerRef = 'TSEL-'.time().'-'.Str::random(6);
        $sn = 'SN-'.date('YmdHis').'-'.rand(1000, 9999);

        return [
            'status' => 'success',
            'provider_ref' => $providerRef,
            'raw' => [
                'provider' => 'Telkomsel',
                'customer_number' => $customerNumber,
                'sku_code' => $productCode,
                'serial_number' => $sn,
                'message' => 'Pengisian pulsa/data Telkomsel berhasil',
            ],
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
            'raw' => [
                'provider' => 'Telkomsel',
                'status' => 'success',
            ],
        ];
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('ppob.providers.telkomsel.webhook_secret') ?: 'telkomsel_secret_key';
        $expected = hash_hmac('sha256', $rawBody, $secret);

        $cleanSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $cleanSignature);
    }
}
