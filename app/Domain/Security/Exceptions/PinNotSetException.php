<?php

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class PinNotSetException extends AppException
{
    public function __construct(string $message = 'PIN belum diatur, silakan set PIN terlebih dahulu.')
    {
        parent::__construct('PIN_NOT_SET', $message, 422);
    }
}
