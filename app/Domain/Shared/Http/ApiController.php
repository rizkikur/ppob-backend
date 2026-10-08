<?php

namespace App\Domain\Shared\Http;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller untuk semua domain.
 *
 * Semua controller di app/Domain/{Domain}/Http/Controllers/ WAJIB extend class ini
 * (bukan Illuminate\Routing\Controller langsung).
 *
 * Menyediakan shortcut ke semua metode ApiResponse.
 */
abstract class ApiController extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        return ApiResponse::success($data, $message, $status, $meta);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return ApiResponse::created($data);
    }

    protected function accepted(mixed $data = null, string $message = 'Accepted'): JsonResponse
    {
        return ApiResponse::accepted($data, $message);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    protected function paginated(LengthAwarePaginator $paginator, callable $transformer): JsonResponse
    {
        return ApiResponse::paginated($paginator, $transformer);
    }

    protected function error(
        string $errorCode,
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        return ApiResponse::error($errorCode, $message, $status, $errors);
    }

    protected function notFound(string $message = 'Resource tidak ditemukan'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function forbidden(string $message = 'Anda tidak memiliki akses'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }

    protected function conflict(string $errorCode, string $message, mixed $data = null): JsonResponse
    {
        return ApiResponse::conflict($errorCode, $message, $data);
    }
}
