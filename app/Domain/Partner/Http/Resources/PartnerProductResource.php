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
    public function __construct($resource, private readonly ?Money $price = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Partner|null $partner */
        $partner = $request->attributes->get('partner');
        $effectivePrice = $this->price ?? ($partner ? $partner->getPriceForProduct($this->resource) : null);

        return [
            'id' => $this->id,
            'sku_code' => $this->sku_code,
            'name' => $this->name,
            'description' => $this->description,
            'product_type' => $this->product_type,
            'price' => $effectivePrice?->toCents(),
            'price_display' => $effectivePrice?->format(),
            'category' => $this->relationLoaded('category') && $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'is_active' => $this->is_active,
        ];
    }
}
