<?php

namespace App\Domain\Transaction\Http\Resources;

use App\Domain\Product\Http\Resources\ProductResource;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product ? new ProductResource($this->product) : null,
            'customer_number' => $this->customer_number,
            'amount' => $this->amount_cents?->toCents(),
            'amount_display' => $this->amount_cents?->format(),
            'sell_price' => $this->sell_price_cents?->toCents(),
            'sell_price_display' => $this->sell_price_cents?->format(),
            'status' => $this->status,
            'provider_ref' => $this->provider_ref,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
