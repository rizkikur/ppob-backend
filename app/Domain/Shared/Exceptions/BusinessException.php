<?php

namespace App\Domain\Shared\Exceptions;

/**
 * Exception untuk pelanggaran aturan bisnis.
 * Contoh: saldo tidak cukup, produk tidak aktif, inquiry sudah expire.
 */
class BusinessException extends AppException
{
    public function __construct(string $errorCode, string $message, int $httpStatus = 422, array $errors = [])
    {
        parent::__construct($errorCode, $message, $httpStatus, $errors);
    }

    public static function insufficientBalance(): self
    {
        return new self('INSUFFICIENT_BALANCE', 'Saldo tidak mencukupi untuk transaksi ini', 422);
    }

    public static function productNotFound(string $sku): self
    {
        return new self('PRODUCT_NOT_FOUND', "Produk dengan SKU '{$sku}' tidak ditemukan", 404);
    }

    public static function productInactive(): self
    {
        return new self('PRODUCT_INACTIVE', 'Produk sedang tidak tersedia', 422);
    }

    public static function productTypeMismatch(string $expected, string $actual): self
    {
        return new self('PRODUCT_TYPE_MISMATCH', "Operasi ini hanya untuk produk {$expected}, bukan {$actual}", 422);
    }

    public static function inquiryRequired(): self
    {
        return new self('INQUIRY_REQUIRED', 'Produk ini memerlukan inquiry sebelum transaksi', 422);
    }

    public static function inquiryExpired(): self
    {
        return new self('INQUIRY_EXPIRED', 'Hasil inquiry sudah kedaluwarsa, silakan inquiry ulang', 422);
    }

    public static function inquiryAmountMismatch(): self
    {
        return new self('INQUIRY_AMOUNT_MISMATCH', 'Nominal tidak sesuai dengan hasil inquiry', 422);
    }

    public static function providerUnavailable(string $provider): self
    {
        return new self('PROVIDER_UNAVAILABLE', "Provider {$provider} sedang tidak tersedia", 503);
    }

    public static function providerError(string $provider, string $detail = ''): self
    {
        $msg = "Provider {$provider} mengembalikan error";
        if ($detail) {
            $msg .= ": {$detail}";
        }

        return new self('PROVIDER_ERROR', $msg, 502);
    }
}
