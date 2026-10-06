<?php

namespace App\Domain\Partner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Partner — mitra bisnis dengan akses Open API.
 * api_key: plaintext (digunakan sebagai identifier lookup).
 * secret: terenkripsi dengan Laravel encrypt(), digunakan untuk HMAC.
 * allowed_ips: array IP whitelist, null = semua IP diizinkan.
 */
class Partner extends Model
{
    protected $table = 'partners';

    protected $fillable = ['name', 'api_key', 'secret', 'allowed_ips', 'is_active', 'rate_limit_rpm'];

    protected $hidden = ['secret'];

    protected $casts = ['allowed_ips' => 'array', 'is_active' => 'boolean', 'rate_limit_rpm' => 'integer'];

    public function getSecretDecrypted(): string
    {
        return decrypt($this->secret);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PartnerLog::class);
    }
}
