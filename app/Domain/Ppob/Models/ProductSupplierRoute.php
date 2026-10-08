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
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
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
}
