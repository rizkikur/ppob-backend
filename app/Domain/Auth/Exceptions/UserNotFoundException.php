<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat user tidak ditemukan.
 * HTTP 404 — error_code: USER_NOT_FOUND
 */
class UserNotFoundException extends AppException
{
    public function __construct(string $message = 'Pengguna tidak ditemukan.')
    {
        parent::__construct('USER_NOT_FOUND', $message, 404);
    }
}
