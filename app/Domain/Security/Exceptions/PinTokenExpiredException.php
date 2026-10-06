<?php

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class PinTokenExpiredException extends AppException
{
    public function __construct(string $message = 'Token PIN sudah kedaluwarsa.')
    {
        parent::__construct('PIN_TOKEN_EXPIRED', $message, 422);
    }
}
