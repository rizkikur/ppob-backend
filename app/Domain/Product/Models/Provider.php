<?php

namespace App\Domain\Product\Models;

use App\Domain\Partner\Models\PartnerRoutingRule;
use App\Domain\Ppob\Models\ProductSupplierRoute;
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
        'callback_slug',
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

    public function supplierRoutes(): HasMany
    {
        return $this->hasMany(ProductSupplierRoute::class, 'provider_id');
    }

    public function preferredRoutingRules(): HasMany
    {
        return $this->hasMany(PartnerRoutingRule::class, 'preferred_provider_id');
    }

    public function errorMappings(): HasMany
    {
        return $this->hasMany(ProviderErrorMapping::class, 'provider_id');
    }

    public function upstreamProducts(): HasMany
    {
        return $this->hasMany(ProviderProduct::class, 'provider_id');
    }

    /**
     * Dapatkan slug callback efektif (fallback ke code jika callback_slug kosong).
     */
    public function getCallbackSlug(): string
    {
        return $this->callback_slug ?: $this->code;
    }

    /**
     * Dapatkan full URL webhook callback yang diberikan ke supplier.
     */
    public function getEffectiveCallbackUrl(): string
    {
        return url('/api/callback/' . $this->getCallbackSlug());
    }

    /**
     * Cek apakah supplier mendukung callback/webhook upstream.
     */
    public function supportsCallback(): bool
    {
        if (isset($this->config['supports_callback'])) {
            return (bool) $this->config['supports_callback'];
        }

        return ! empty(config("ppob.providers.{$this->code}.webhook_secret"));
    }

    /**
     * Cek apakah supplier mendukung API check status / inquiry requery.
     */
    public function supportsCheckStatus(): bool
    {
        return (bool) ($this->config['supports_check_status'] ?? true);
    }
}
