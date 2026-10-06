<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat rate limit OTP terlampaui (maks 3 request per 10 menit).
 * HTTP 429 — error_code: OTP_RATE_LIMIT_EXCEEDED
 */
class OtpRateLimitException extends AppException
{
    public function __construct(string $message = 'Terlalu banyak permintaan OTP. Coba lagi dalam 10 menit.')
    {
        parent::__construct('OTP_RATE_LIMIT_EXCEEDED', $message, 429);
    }
}
