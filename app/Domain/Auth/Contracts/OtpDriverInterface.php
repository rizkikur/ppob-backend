<?php

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Exceptions\OtpDeliveryException;

/**
 * Interface untuk driver pengiriman OTP.
 *
 * Implementasi:
 * - FonnteOtpDriver — via Fonnte WhatsApp API
 * - WaBizOtpDriver  — via WhatsApp Business API resmi
 * - FakeOtpDriver   — untuk testing (tidak HTTP)
 *
 * Driver aktif dikonfigurasi via DomainServiceProvider.
 */
interface OtpDriverInterface
{
    /**
     * Kirim OTP ke nomor WhatsApp.
     *
     * @param  string  $phone  Nomor telepon
     * @param  string  $code  Kode OTP 6 digit
     *
     * @throws OtpDeliveryException jika pengiriman gagal
     */
    public function send(string $phone, string $code): void;
}
