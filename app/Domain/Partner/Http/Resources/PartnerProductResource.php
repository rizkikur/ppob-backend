<?php

namespace App\Domain\Partner\Http\Resources;

use App\Domain\Partner\Models\Partner;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class PartnerProductResource extends JsonResource
{
    protected ?Money $price;

    public function __construct($resource, ?Money $price = null)
    {
        parent::__construct($resource);
        $this->price = $price;
    }

    public function toArray(Request $request): array
    {
        /** @var Partner|null $partner */
        $partner = $request->attributes->get('partner');
        $effectivePrice = $this->price !== null ? $this->price : ($partner ? $partner->getPriceForProduct($this->resource) : null);
        $category = $this->relationLoaded('category') && $this->category ? $this->category : null;

        return [
            'id' => $this->id,
            'sku_code' => $this->sku_code,
            'name' => $this->name,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'price' => $effectivePrice ? $effectivePrice->toCents() : null,
            'price_display' => $effectivePrice ? $effectivePrice->format() : null,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ] : null,
            'is_active' => $this->is_active,
        ];
    }
}
