<?php

namespace App\Domain\Shared\Middleware;

use App\Domain\Security\Models\PinVerificationToken;
use App\Domain\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware validasi X-Pin-Token header.
 *
 * Memastikan setiap endpoint transaksi yang sensitif hanya dapat diakses
 * setelah user melewati alur verifikasi PIN.
 *
 * Alur PIN (lihat docs/security-design.md bagian 2):
 *   POST /security/pin/challenge → POST /security/pin/verify → X-Pin-Token → endpoint ini
 *
 * Aturan validasi:
 * - Token harus ada di tabel pin_verification_tokens
 * - Token milik user yang sedang login
 * - Token belum dipakai (used_at IS NULL)
 * - Token belum expire (expires_at > now())
 * - Token purpose harus cocok (jika diberikan)
 *
 * Setelah validasi sukses, token langsung di-mark sebagai used.
 */
class PinTokenMiddleware
{
    /**
     * @param  string|null  $purpose  Purpose yang diharapkan (opsional)
     */
    public function handle(Request $request, Closure $next, ?string $purpose = null): Response
    {
        $tokenValue = $request->header('X-Pin-Token');

        if (! $tokenValue) {
            return ApiResponse::error(
                'PIN_TOKEN_INVALID',
                'Header X-Pin-Token wajib diisi untuk endpoint ini',
                422
            );
        }

        $userId = Auth::id();

        $token = PinVerificationToken::where('token', $tokenValue)
            ->where('user_id', $userId)
            ->first();

        if (! $token) {
            return ApiResponse::error('PIN_TOKEN_INVALID', 'Token PIN tidak valid', 422);
        }

        if ($token->used_at !== null) {
            return ApiResponse::error('PIN_TOKEN_USED', 'Token PIN sudah pernah digunakan', 422);
        }

        if ($token->expires_at->isPast()) {
            return ApiResponse::error('PIN_TOKEN_EXPIRED', 'Token PIN sudah kedaluwarsa', 422);
        }

        if ($purpose && $token->purpose !== $purpose) {
            return ApiResponse::error(
                'PIN_TOKEN_INVALID',
                'Token PIN tidak valid untuk tujuan ini',
                422
            );
        }

        // Tandai token sebagai sudah dipakai (sekali pakai)
        $token->update(['used_at' => now()]);

        // Tambahkan token ke request untuk diakses di controller jika perlu
        $request->attributes->set('pin_token', $token);

        return $next($request);
    }
}
