<?php

namespace App\Domain\Partner\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model PartnerProductPrice — Harga jual khusus per partner per produk (ADR-006).
 */
class PartnerProductPrice extends Model
{
    protected $table = 'partner_product_prices';

    protected $fillable = [
        'partner_id',
        'product_id',
        'sell_price_cents',
        'is_active',
    ];

    protected $casts = [
        'sell_price_cents' => MoneyCast::class,
        'is_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
