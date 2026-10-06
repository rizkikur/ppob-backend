<?php

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class PinTokenUsedException extends AppException
{
    public function __construct(string $message = 'Token PIN sudah pernah digunakan.')
    {
        parent::__construct('PIN_TOKEN_USED', $message, 422);
    }
}
