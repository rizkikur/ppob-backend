<?php

namespace App\Domain\Wallet\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class TopupAlreadyProcessedException extends AppException
{
    public function __construct(string $message = 'Permintaan top-up sudah diproses sebelumnya')
    {
        parent::__construct('VALIDATION_ERROR', $message, 422);
    }
}
