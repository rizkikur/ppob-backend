<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat OTP sudah kedaluwarsa.
 * HTTP 422 — error_code: OTP_EXPIRED
 */
class OtpExpiredException extends AppException
{
    public function __construct(string $message = 'Kode OTP sudah kedaluwarsa.')
    {
        parent::__construct('OTP_EXPIRED', $message, 422);
    }
}
