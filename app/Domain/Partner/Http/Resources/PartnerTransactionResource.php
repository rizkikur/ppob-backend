<?php

namespace App\Domain\Partner\Http\Resources;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class PartnerTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $amountCents = $this->amount_cents;
        $sellPriceCents = $this->sell_price_cents;
        $createdAt = $this->created_at;
        $updatedAt = $this->updated_at;

        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'sku_code' => $product ? $product->sku_code : null,
            'product_name' => $product ? $product->name : null,
            'customer_number' => $this->customer_number,
            'amount' => $amountCents ? $amountCents->toCents() : null,
            'amount_display' => $amountCents ? $amountCents->format() : null,
            'price' => $sellPriceCents ? $sellPriceCents->toCents() : null,
            'price_display' => $sellPriceCents ? $sellPriceCents->format() : null,
            'status' => $this->status,
            'idempotency_key' => $this->idempotency_key,
            'provider_ref' => $this->provider_ref,
            'failure_reason' => $this->failure_reason,
            'created_at' => $createdAt ? $createdAt->toISOString() : null,
            'updated_at' => $updatedAt ? $updatedAt->toISOString() : null,
        ];
    }
}
