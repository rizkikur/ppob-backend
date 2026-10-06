<?php

namespace App\Domain\Wallet\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class TopupNotFoundException extends AppException
{
    public function __construct(string $message = 'Permintaan top-up tidak ditemukan')
    {
        parent::__construct('RESOURCE_NOT_FOUND', $message, 404);
    }
}
