<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat driver OTP gagal mengirim pesan.
 * HTTP 503 — error_code: OTP_DELIVERY_FAILED
 */
class OtpDeliveryException extends AppException
{
    public function __construct(string $message = 'Gagal mengirim kode OTP. Silakan coba beberapa saat lagi.')
    {
        parent::__construct('OTP_DELIVERY_FAILED', $message, 503);
    }
}
