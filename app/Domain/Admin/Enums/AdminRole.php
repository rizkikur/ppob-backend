<?php

namespace App\Domain\Admin\Enums;

enum AdminRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case OPS_ADMIN = 'ops_admin';
    case FINANCE_ADMIN = 'finance_admin';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::OPS_ADMIN => 'Operations & Support',
            self::FINANCE_ADMIN => 'Finance & Settlement',
        };
    }
}
