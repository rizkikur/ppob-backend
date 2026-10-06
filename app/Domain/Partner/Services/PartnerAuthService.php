<?php

namespace App\Domain\Partner\Services;

use App\Domain\Partner\Models\Partner;
use App\Domain\Shared\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Service autentikasi partner Open API.
 * Validasi: API Key + timestamp + HMAC signature (lihat docs/security-design.md bagian 5).
 */
class PartnerAuthService
{
    private const TIMESTAMP_TOLERANCE = 300; // 5 menit

    /** Validasi request partner secara penuh. @throws BusinessException */
    public function validate(Request $request): Partner
    {
        $apiKey = $request->header('X-Api-Key');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        if (! $apiKey) {
            throw new BusinessException('PARTNER_KEY_INVALID', 'X-Api-Key header wajib diisi', 401);
        }
        $partner = Partner::where('api_key', $apiKey)->where('is_active', true)->first();
        if (! $partner) {
            throw new BusinessException('PARTNER_KEY_INVALID', 'API Key tidak valid', 401);
        }
        // Cek IP whitelist
        if ($partner->allowed_ips && ! in_array($request->ip(), $partner->allowed_ips)) {
            throw new BusinessException('PARTNER_IP_BLOCKED', 'IP tidak ada dalam whitelist', 403);
        }
        // Cek timestamp
        if (! $timestamp || abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            throw new BusinessException('WEBHOOK_TIMESTAMP_INVALID', 'Timestamp di luar toleransi', 422);
        }
        // Cek rate limit
        $this->enforceRateLimit($partner);
        // Verifikasi HMAC signature
        $this->verifySignature($request, $partner, $timestamp, $signature);

        return $partner;
    }

    private function enforceRateLimit(Partner $partner): void
    {
        $key = "partner:{$partner->id}:rpm";
        $count = (int) Cache::get($key, 0);
        if ($count >= $partner->rate_limit_rpm) {
            throw new BusinessException('PARTNER_RATE_LIMITED', 'Anda telah melampaui batas request per menit', 429);
        }
        Cache::increment($key);
        Cache::put($key, Cache::get($key, 1), now()->addMinute());
    }

    private function verifySignature(Request $request, Partner $partner, string $timestamp, ?string $signature): void
    {
        if (! $signature) {
            throw new BusinessException('PARTNER_SIGNATURE_INVALID', 'X-Signature header wajib diisi', 401);
        }
        $rawBody = $request->getContent();
        $bodyHash = hash('sha256', $rawBody);
        $stringToSign = $request->method()."\n".$request->path()."\n".$timestamp."\n".$bodyHash;
        $expected = hash_hmac('sha256', $stringToSign, $partner->getSecretDecrypted());
        // Constant-time compare untuk mencegah timing attack
        if (! hash_equals($expected, $signature)) {
            throw new BusinessException('PARTNER_SIGNATURE_INVALID', 'Signature tidak valid', 401);
        }
    }
}
