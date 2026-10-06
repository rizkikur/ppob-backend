<?php

namespace App\Domain\Partner\Services;

use App\Domain\Partner\Models\Partner;
use App\Domain\Shared\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Service autentikasi partner Open API.
 * Validasi: API Key + timestamp + IP whitelist + rate limit + HMAC signature (docs/security-design.md bagian 5).
 */
class PartnerAuthService
{
    private const TIMESTAMP_TOLERANCE = 300; // 5 menit

    /**
     * Validasi request partner secara penuh.
     *
     * @throws BusinessException
     */
    public function validate(Request $request): Partner
    {
        $apiKey = $request->header('X-Api-Key');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! $apiKey) {
            throw new BusinessException('PARTNER_KEY_INVALID', 'X-Api-Key header wajib diisi', 401);
        }

        $partner = Partner::where('api_key', $apiKey)->first();
        if (! $partner || ! $partner->is_active) {
            throw new BusinessException('PARTNER_KEY_INVALID', 'API Key tidak valid atau partner tidak aktif', 401);
        }

        $request->attributes->set('partner', $partner);

        // Cek IP whitelist
        if ($partner->allowed_ips && ! empty($partner->allowed_ips)) {
            $clientIp = $request->ip();
            if (! in_array($clientIp, $partner->allowed_ips, true)) {
                throw new BusinessException('PARTNER_IP_BLOCKED', 'IP tidak ada dalam whitelist partner', 403);
            }
        }

        // Cek timestamp
        if (! $timestamp || abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            throw new BusinessException('WEBHOOK_TIMESTAMP_INVALID', 'Timestamp di luar toleransi (maks 300 detik)', 422);
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

        if (! Cache::has($key)) {
            Cache::put($key, 1, 60);
        } else {
            Cache::increment($key);
        }
    }

    private function verifySignature(Request $request, Partner $partner, string $timestamp, ?string $signature): void
    {
        if (! $signature) {
            throw new BusinessException('PARTNER_SIGNATURE_INVALID', 'X-Signature header wajib diisi', 401);
        }

        $rawBody = $request->getContent();
        $bodyHashes = [hash('sha256', $rawBody)];
        if ($rawBody === '[]' || $rawBody === '{}' || $rawBody === '') {
            $bodyHashes[] = hash('sha256', '');
            $bodyHashes[] = hash('sha256', '[]');
            $bodyHashes[] = hash('sha256', '{}');
        }
        $bodyHashes = array_unique($bodyHashes);

        $pathWithSlash = '/'.ltrim($request->path(), '/');
        $pathWithoutSlash = ltrim($request->path(), '/');
        $paths = array_unique([$pathWithSlash, $pathWithoutSlash]);

        $secret = $partner->getSecretDecrypted();
        $cleanSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        $isValid = false;
        foreach ($paths as $path) {
            foreach ($bodyHashes as $bHash) {
                $stringToSign = strtoupper($request->method())."\n".$path."\n".$timestamp."\n".$bHash;
                $expected = hash_hmac('sha256', $stringToSign, $secret);
                if (hash_equals($expected, $cleanSignature)) {
                    $isValid = true;
                    break 2;
                }
            }
        }

        if (! $isValid) {
            throw new BusinessException('PARTNER_SIGNATURE_INVALID', 'Signature tidak valid', 401);
        }
    }
}
