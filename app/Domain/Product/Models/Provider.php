<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Provider — provider PPOB (Telkomsel, PLN, PDAM, dsb).
 * Field 'driver' berisi nama class driver (contoh: TelkomselDriver).
 * Field 'config' berisi konfigurasi API (dienkripsi di aplikasi).
 */
class Provider extends Model
{
    protected $table = 'providers';

    protected $fillable = [
        'name',
        'code',
        'driver',
        'config',
        'queue_name',
        'max_workers',
        'rate_limit_per_minute',
        'timeout_seconds',
        'priority',
        'supports_balance_api',
        'balance_alert_threshold_cents',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'max_workers' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'timeout_seconds' => 'integer',
        'priority' => 'integer',
        'supports_balance_api' => 'boolean',
        'balance_alert_threshold_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'provider_id');
    }
}
