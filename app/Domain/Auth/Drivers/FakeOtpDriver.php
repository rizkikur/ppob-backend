<?php

namespace App\Domain\Auth\Drivers;

use App\Domain\Auth\Contracts\OtpDriverInterface;

/**
 * Driver OTP palsu untuk keperluan testing.
 *
 * Tidak melakukan HTTP request — hanya menyimpan kode ke array.
 * Gunakan di test: $this->instance(OtpDriverInterface::class, new FakeOtpDriver())
 */
class FakeOtpDriver implements OtpDriverInterface
{
    /** @var array<array{phone: string, code: string}> */
    public array $sent = [];

    /**
     * Catat pengiriman OTP tanpa HTTP.
     */
    public function send(string $phone, string $code): void
    {
        $this->sent[] = ['phone' => $phone, 'code' => $code];
    }

    /**
     * Apakah ada OTP yang dikirim ke nomor ini?
     */
    public function wasSentTo(string $phone): bool
    {
        foreach ($this->sent as $entry) {
            if ($entry['phone'] === $phone) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ambil kode OTP terakhir yang dikirim ke nomor ini.
     */
    public function lastCodeFor(string $phone): ?string
    {
        $matching = array_filter($this->sent, fn ($e) => $e['phone'] === $phone);

        if (empty($matching)) {
            return null;
        }

        return end($matching)['code'];
    }

    /**
     * Reset semua record pengiriman.
     */
    public function reset(): void
    {
        $this->sent = [];
    }
}
