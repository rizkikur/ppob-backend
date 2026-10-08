<?php

namespace App\Domain\Product\Models;

use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Product — katalog produk PPOB.
 * product_type: 'prepaid' atau 'postpaid'
 * Aturan keras: untuk prepaid, amount dari klien diabaikan (harga dari server).
 * Untuk postpaid, wajib inquiry dulu, amount harus cocok.
 */
class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'provider_id',
        'category_id',
        'sku_code',
        'name',
        'description',
        'product_type',
        'base_price_cents',
        'admin_fee_cents',
        'is_active',
    ];

    protected $casts = [
        'base_price_cents' => MoneyCast::class,
        'admin_fee_cents' => MoneyCast::class,
        'is_active' => 'boolean',
    ];

    public const TYPE_PREPAID = 'prepaid';

    public const TYPE_POSTPAID = 'postpaid';

    public function isPrepaid(): bool
    {
        return $this->product_type === self::TYPE_PREPAID;
    }

    public function isPostpaid(): bool
    {
        return $this->product_type === self::TYPE_POSTPAID;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function tierPrices(): HasMany
    {
        return $this->hasMany(ProductTierPrice::class);
    }

    public function supplierRoutes(): HasMany
    {
        return $this->hasMany(ProductSupplierRoute::class);
    }
}
