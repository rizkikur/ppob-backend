<?php

namespace App\Domain\Inquiry\Http\Resources;

use App\Domain\Inquiry\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Inquiry */
class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_number' => $this->customer_number,
            'amount' => $this->amount_cents?->toCents(),
            'amount_display' => $this->amount_cents?->format(),
            'admin_fee' => $this->admin_fee_cents?->toCents(),
            'admin_fee_display' => $this->admin_fee_cents?->format(),
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
