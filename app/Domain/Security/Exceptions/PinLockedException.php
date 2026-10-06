<?php

namespace App\Domain\Security\Exceptions;

use App\Domain\Shared\Exceptions\AppException;

class PinLockedException extends AppException
{
    public function __construct(string $message = 'Terlalu banyak percobaan PIN. Akun terkunci sementara selama 15 menit.')
    {
        parent::__construct('PIN_LOCKED', $message, 429);
    }
}
