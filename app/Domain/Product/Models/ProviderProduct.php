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
}
