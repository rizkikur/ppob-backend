<?php

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class PinInvalidException extends AppException
{
    public function __construct(string $message = 'PIN tidak sesuai.')
    {
        parent::__construct('PIN_INVALID', $message, 422);
    }
}
