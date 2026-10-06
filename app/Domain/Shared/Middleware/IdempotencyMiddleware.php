<?php

namespace App\Domain\Shared\Middleware;

use App\Domain\Shared\Http\ApiResponse;
use App\Domain\Transaction\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware idempotency — mencegah pemrosesan ganda untuk request yang sama.
 *
 * Cara kerja:
 * 1. Cek header Idempotency-Key
 * 2. Cek tabel idempotency_keys untuk (user_id, key_value) yang sudah ada
 * 3. Jika ada → kembalikan response pertama dengan HTTP 409
 * 4. Jika belum ada → proses request, simpan response ke tabel
 *
 * Aturan keras: semua endpoint yang mengubah saldo/stok WAJIB menggunakan middleware ini.
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return ApiResponse::error(
                'IDEMPOTENCY_KEY_REQUIRED',
                'Header Idempotency-Key wajib diisi',
                422
            );
        }

        if (strlen($key) > 100) {
            return ApiResponse::error(
                'VALIDATION_ERROR',
                'Idempotency-Key tidak boleh lebih dari 100 karakter',
                422
            );
        }

        $userId = $request->user()?->id ?? Auth::id();

        // Cek apakah key sudah pernah dipakai
        $existing = IdempotencyKey::where('user_id', $userId)
            ->where('key_value', $key)
            ->first();

        if ($existing) {
            $data = is_array($existing->response_body)
                ? $existing->response_body
                : json_decode($existing->response_body, true);

            // Kembalikan response asli dari request pertama
            return ApiResponse::conflict(
                'IDEMPOTENCY_CONFLICT',
                'Request dengan Idempotency-Key ini sudah pernah diproses',
                $data
            );
        }

        // Proses request
        $response = $next($request);

        // Simpan response ke tabel idempotency_keys (hanya untuk status 2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $decodedBody = json_decode($response->getContent(), true);

            IdempotencyKey::create([
                'user_id' => $userId,
                'key_value' => $key,
                'endpoint' => $request->path(),
                'response_code' => $response->getStatusCode(),
                'response_body' => $decodedBody ?? $response->getContent(),
            ]);
        }

        return $response;
    }
}
