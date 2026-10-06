<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat OTP melebihi batas percobaan (maks 3).
 * HTTP 422 — error_code: OTP_MAX_ATTEMPTS_EXCEEDED
 */
class OtpExhaustedException extends AppException
{
    public function __construct(string $message = 'Kode OTP telah melebihi batas percobaan. Silakan minta kode baru.')
    {
        parent::__construct('OTP_MAX_ATTEMPTS_EXCEEDED', $message, 422);
    }
}
