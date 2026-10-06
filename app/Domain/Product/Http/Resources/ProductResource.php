<?php

namespace App\Domain\Product\Http\Resources;

use App\Domain\Product\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function __construct($resource, private readonly ?Money $sellPrice = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku_code' => $this->sku_code,
            'name' => $this->name,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'sell_price' => $this->sellPrice?->toCents(),
            'sell_price_display' => $this->sellPrice?->format(),
            'provider' => $this->relationLoaded('provider') ? $this->provider?->name : null,
            'category' => $this->relationLoaded('category') && $this->category ? new CategoryResource($this->category) : null,
            'is_active' => $this->is_active,
        ];
    }
}
