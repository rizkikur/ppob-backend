<?php

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class PinTokenInvalidException extends AppException
{
    public function __construct(string $message = 'Token PIN tidak valid.')
    {
        parent::__construct('PIN_TOKEN_INVALID', $message, 422);
    }
}
