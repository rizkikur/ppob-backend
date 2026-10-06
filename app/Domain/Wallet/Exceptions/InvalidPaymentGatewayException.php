<?php

namespace App\Domain\Wallet\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class InvalidPaymentGatewayException extends AppException
{
    public function __construct(string $message = 'Payment gateway tidak didukung')
    {
        parent::__construct('VALIDATION_ERROR', $message, 422);
    }
}
