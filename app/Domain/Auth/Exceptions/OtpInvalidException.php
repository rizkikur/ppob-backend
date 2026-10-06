<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat kode OTP tidak cocok.
 * HTTP 422 — error_code: OTP_INVALID
 */
class OtpInvalidException extends AppException
{
    public function __construct(string $message = 'Kode OTP tidak valid.')
    {
        parent::__construct('OTP_INVALID', $message, 422);
    }
}
