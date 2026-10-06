<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\OtpDriverInterface;
use App\Domain\Auth\Exceptions\OtpDeliveryException;
use App\Domain\Auth\Exceptions\OtpExhaustedException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Domain\Auth\Exceptions\OtpInvalidException;
use App\Domain\Auth\Exceptions\OtpRateLimitException;
use App\Domain\Auth\Models\OtpCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk manajemen OTP.
 *
 * Tanggung jawab:
 * - Generate kode OTP
 * - Enforce rate limit via Redis/Cache (maks 3 request per 10 menit per phone+purpose)
 * - Kirim OTP via driver yang dikonfigurasi
 * - Verifikasi kode OTP dengan row-lock
 *
 * Field mapping (spec → DB):
 *   purpose    → type
 *   attempt_count → attempts
 *   used_at    → verified_at
 */
class OtpService
{
    private const OTP_EXPIRE_MIN = 5;

    private const RATE_LIMIT_MAX = 3;

    private const RATE_LIMIT_TTL_MIN = 10;

    public function __construct(
        private readonly OtpDriverInterface $driver
    ) {}

    /**
     * Kirim OTP ke nomor telepon.
     *
     * @param  string  $phone  Nomor telepon tujuan
     * @param  string  $purpose  Purpose: register|login|reset_pin
     *
     * @throws OtpRateLimitException jika sudah 3 request dalam 10 menit
     * @throws OtpDeliveryException jika driver gagal mengirim
     */
    public function send(string $phone, string $purpose): void
    {
        // 1. Cek rate limit di Redis
        $rateLimitKey = "otp:rl:{$phone}:{$purpose}";
        $count = (int) Cache::get($rateLimitKey, 0);

        if ($count >= self::RATE_LIMIT_MAX) {
            throw new OtpRateLimitException;
        }

        // 2. Generate 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 3. Insert ke otp_codes dalam DB transaction
        DB::transaction(function () use ($phone, $code, $purpose) {
            OtpCode::create([
                'phone' => $phone,
                'code' => $code,
                'type' => $purpose,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRE_MIN),
            ]);
        });

        // 4. Increment Redis counter, set TTL jika baru
        if ($count === 0) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(self::RATE_LIMIT_TTL_MIN));
        } else {
            Cache::increment($rateLimitKey);
        }

        // 5. Panggil driver (throw OtpDeliveryException jika gagal)
        $this->driver->send($phone, $code);
    }

    /**
     * Verifikasi kode OTP.
     *
     * @param  string  $phone  Nomor telepon
     * @param  string  $code  Kode OTP yang dimasukkan user
     * @param  string  $purpose  Purpose: register|login|reset_pin
     * @return OtpCode OtpCode yang terverifikasi
     *
     * @throws OtpExpiredException jika OTP kadaluarsa
     * @throws OtpExhaustedException jika melebihi maks percobaan
     * @throws OtpInvalidException jika kode salah
     */
    public function verify(string $phone, string $code, string $purpose): OtpCode
    {
        // 1. Ambil OTP terbaru yang belum verified, sesuai purpose
        $otpCode = OtpCode::where('phone', $phone)
            ->where(function ($q) use ($purpose) {
                $q->where('type', $purpose)->orWhere('type', $purpose);
            })
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otpCode) {
            throw new OtpInvalidException('Kode OTP tidak ditemukan.');
        }

        // 2. Cek expired
        if ($otpCode->isExpired()) {
            throw new OtpExpiredException;
        }

        // 3. Cek exhausted (max attempts)
        if ($otpCode->isExhausted()) {
            throw new OtpExhaustedException;
        }

        // 4. Increment attempts (harus tersimpan di DB meskipun kode salah)
        $otpCode->increment('attempts');

        if ($otpCode->code !== $code) {
            throw new OtpInvalidException;
        }

        // Kode benar — tandai sebagai digunakan
        $otpCode->update(['verified_at' => now()]);

        return $otpCode->fresh();
    }
}
