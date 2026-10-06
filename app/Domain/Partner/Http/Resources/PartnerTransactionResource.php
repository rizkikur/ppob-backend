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
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'sku_code' => $this->product?->sku_code,
            'product_name' => $this->product?->name,
            'customer_number' => $this->customer_number,
            'amount' => $this->amount_cents?->toCents(),
            'amount_display' => $this->amount_cents?->format(),
            'price' => $this->sell_price_cents?->toCents(),
            'price_display' => $this->sell_price_cents?->format(),
            'status' => $this->status,
            'idempotency_key' => $this->idempotency_key,
            'provider_ref' => $this->provider_ref,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
