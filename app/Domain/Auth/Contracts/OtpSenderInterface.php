<?php

namespace App\Domain\Auth\Contracts;

/**
 * Interface untuk driver pengiriman OTP.
 *
 * Implementasi:
 * - FonnteOtpDriver — via Fonnte WhatsApp API
 * - WaBizOtpDriver  — via WhatsApp Business API resmi
 *
 * Driver aktif dikonfigurasi via config/ppob.php → otp_driver.
 */
interface OtpSenderInterface
{
    /**
     * Kirim OTP ke nomor WhatsApp.
     *
     * @param  string  $phone  Nomor telepon format E.164 (+6281xxx)
     * @param  string  $code  Kode OTP 6 digit
     * @return bool True jika pengiriman berhasil
     */
    public function send(string $phone, string $code): bool;
}
