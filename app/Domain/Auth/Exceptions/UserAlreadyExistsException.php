<?php

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

/**
 * Dilempar saat user mencoba registrasi dengan nomor yang sudah terdaftar.
 * HTTP 409 — error_code: USER_ALREADY_EXISTS
 */
class UserAlreadyExistsException extends AppException
{
    public function __construct(string $message = 'Nomor telepon sudah terdaftar.')
    {
        parent::__construct('USER_ALREADY_EXISTS', $message, 409);
    }
}
