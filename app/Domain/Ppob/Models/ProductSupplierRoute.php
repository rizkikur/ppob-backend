<?php

namespace App\Domain\Ppob\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model ProductSupplierRoute — pemetaan multi-supplier per produk (ADR-004).
 * Menyimpan daftar supplier yang dapat melayani SKU produk tertentu beserta prioritasnya.
 */
class ProductSupplierRoute extends Model
{
    protected $table = 'product_supplier_routes';

    protected $fillable = [
        'product_id',
        'provider_id',
        'supplier_sku',
        'priority',
        'cost_cents',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'cost_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Dapatkan SKU yang dikirim ke upstream (fallback ke SKU internal jika supplier_sku belum diisi).
     */
    public function getEffectiveSku(): string
    {
        return $this->supplier_sku ?: ($this->product?->sku_code ?? '');
    }

    /**
     * Mutator agar cost_cents dapat menerima integer atau instance Money.
     */
    public function setCostCentsAttribute(mixed $value): void
    {
        if ($value instanceof \App\Domain\Shared\ValueObjects\Money) {
            $this->attributes['cost_cents'] = $value->toCents();
        } elseif (is_numeric($value)) {
            $this->attributes['cost_cents'] = (int) $value;
        } else {
            $this->attributes['cost_cents'] = null;
        }
    }

    /**
     * Accessor untuk mendapatkan nilai cost_cents dalam bentuk Money value object.
     */
    public function getCostMoneyAttribute(): ?\App\Domain\Shared\ValueObjects\Money
    {
        return $this->cost_cents !== null ? \App\Domain\Shared\ValueObjects\Money::fromCents($this->cost_cents) : null;
    }
}
