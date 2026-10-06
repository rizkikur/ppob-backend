<?php

namespace App\Domain\Product\Models;

use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Harga jual produk per user tier (end_user, agent, reseller). */
class ProductTierPrice extends Model
{
    protected $table = 'product_tier_prices';

    protected $fillable = [
        'product_id',
        'user_tier_id',
        'sell_price_cents',
    ];

    protected $casts = [
        'sell_price_cents' => MoneyCast::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function userTier(): BelongsTo
    {
        return $this->belongsTo(UserTier::class);
    }
}
