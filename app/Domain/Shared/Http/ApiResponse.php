<?php

namespace App\Domain\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Response envelope standar untuk semua endpoint API.
 *
 * Format sukses:
 * {
 *   "success": true,
 *   "data": { ... },
 *   "meta": { "page": 1, ... }
 * }
 *
 * Format error:
 * {
 *   "success": false,
 *   "error_code": "INSUFFICIENT_BALANCE",
 *   "message": "...",
 *   "errors": {}
 * }
 *
 * Aturan keras: semua controller WAJIB menggunakan class ini —
 * tidak boleh return response()->json() mentah.
 */
class ApiResponse
{
    /**
     * Response sukses dengan data tunggal atau koleksi.
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response sukses dengan paginasi.
     * Meta diisi otomatis dari objek paginator.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        callable $transformer
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map($transformer)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Response sukses untuk pembuatan resource baru (HTTP 201).
     */
    public static function created(mixed $data = null): JsonResponse
    {
        return self::success($data, 'Created', 201);
    }

    /**
     * Response diterima untuk pemrosesan async (HTTP 202).
     */
    public static function accepted(mixed $data = null, string $message = 'Accepted'): JsonResponse
    {
        return self::success($data, $message, 202);
    }

    /**
     * Response sukses tanpa body (HTTP 204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Response error dengan error_code standar.
     *
     * Error code WAJIB berasal dari daftar di docs/security-design.md bagian 11.
     */
    public static function error(
        string $errorCode,
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response 422 Unprocessable Entity (validasi gagal).
     */
    public static function validationError(array $errors, string $message = 'Input tidak valid'): JsonResponse
    {
        return self::error('VALIDATION_ERROR', $message, 422, $errors);
    }

    /**
     * Response 401 Unauthenticated.
     */
    public static function unauthenticated(string $message = 'Autentikasi diperlukan'): JsonResponse
    {
        return self::error('UNAUTHENTICATED', $message, 401);
    }

    /**
     * Response 403 Forbidden.
     */
    public static function forbidden(string $message = 'Anda tidak memiliki akses ke resource ini'): JsonResponse
    {
        return self::error('FORBIDDEN', $message, 403);
    }

    /**
     * Response 404 Not Found.
     */
    public static function notFound(string $message = 'Resource tidak ditemukan'): JsonResponse
    {
        return self::error('RESOURCE_NOT_FOUND', $message, 404);
    }

    /**
     * Response 409 Conflict (idempotency atau duplikat).
     */
    public static function conflict(string $errorCode, string $message, mixed $data = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, 409);
    }

    /**
     * Response 429 Too Many Requests.
     */
    public static function tooManyRequests(string $errorCode, string $message): JsonResponse
    {
        return self::error($errorCode, $message, 429);
    }

    /**
     * Response 500 Internal Server Error.
     */
    public static function serverError(string $message = 'Terjadi kesalahan pada server'): JsonResponse
    {
        return self::error('INTERNAL_ERROR', $message, 500);
    }
}
