<?php

namespace App\Domain\Product\Http\Resources;

use App\Domain\Product\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductCategory */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'icon_url' => $this->icon_url];
    }
}
