<?php

namespace App\Domain\Auth\Drivers;

use App\Domain\Auth\Contracts\OtpSenderInterface;

/** Driver OTP via WhatsApp Business API resmi. TODO: Implementasi di Phase 1. */
class WaBizOtpDriver implements OtpSenderInterface
{
    public function send(string $phone, string $code): bool
    {
        throw new \RuntimeException('WaBizOtpDriver not implemented yet');
    }
}
