<?php

namespace App\Domain\Wallet\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class InvalidWebhookSignatureException extends AppException
{
    public function __construct(string $message = 'Signature webhook tidak valid')
    {
        parent::__construct('WEBHOOK_SIGNATURE_INVALID', $message, 401);
    }
}
