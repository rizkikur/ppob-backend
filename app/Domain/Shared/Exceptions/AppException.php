<?php

namespace App\Domain\Shared\Exceptions;

use App\Domain\Shared\Http\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base exception untuk semua exception domain.
 *
 * Setiap exception domain harus menyertakan error_code yang ada
 * dalam daftar standar di docs/security-design.md bagian 11.
 */
abstract class AppException extends Exception
{
    public function __construct(
        protected readonly string $errorCode,
        string $message,
        protected readonly int $httpStatus = 400,
        protected readonly array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            $this->errorCode,
            $this->getMessage(),
            $this->httpStatus,
            $this->errors
        );
    }
}
