<?php

namespace App\Domain\Product\Models;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderProduct extends Model
{
    protected $table = 'provider_products';

    protected $fillable = [
        'provider_id',
        'supplier_sku',
        'name',
        'category_name',
        'cost_cents',
        'status',
        'inquiry_url',
        'payment_url',
        'raw_data',
        'last_synced_at',
    ];

    protected $casts = [
        'cost_cents' => 'integer',
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function cost(): Money
    {
        return Money::fromCents($this->cost_cents);
    }

    /**
     * Dapatkan full URL inquiry produk (menggabungkan base_url supplier jika berupa path relatif).
     */
    public function getEffectiveInquiryUrl(): ?string
    {
        if (! $this->inquiry_url) {
            return null;
        }

        if (str_starts_with($this->inquiry_url, 'http://') || str_starts_with($this->inquiry_url, 'https://')) {
            return $this->inquiry_url;
        }

        $baseUrl = rtrim((string) ($this->provider?->config['base_url'] ?? $this->provider?->config['api_url'] ?? ''), '/');

        return $baseUrl ? $baseUrl . '/' . ltrim($this->inquiry_url, '/') : $this->inquiry_url;
    }

    /**
     * Dapatkan full URL payment/order produk (menggabungkan base_url supplier jika berupa path relatif).
     */
    public function getEffectivePaymentUrl(): ?string
    {
        if (! $this->payment_url) {
            return null;
        }

        if (str_starts_with($this->payment_url, 'http://') || str_starts_with($this->payment_url, 'https://')) {
            return $this->payment_url;
        }

        $baseUrl = rtrim((string) ($this->provider?->config['base_url'] ?? $this->provider?->config['api_url'] ?? ''), '/');

        return $baseUrl ? $baseUrl . '/' . ltrim($this->payment_url, '/') : $this->payment_url;
    }
}
