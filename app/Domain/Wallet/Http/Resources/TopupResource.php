<?php

namespace App\Domain\Wallet\Http\Resources;

use App\Domain\Wallet\Models\TopupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TopupRequest */
class TopupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount_cents?->toCents(),
            'amount_display' => $this->amount_cents?->format(),
            'method' => $this->method,
            'payment_gateway' => $this->payment_gateway,
            'gateway_ref' => $this->gateway_ref,
            'payment_url' => $this->getPaymentUrl(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
