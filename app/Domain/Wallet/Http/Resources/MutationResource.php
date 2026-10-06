<?php

namespace App\Domain\Wallet\Http\Resources;

use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletMutation */
class MutationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount_cents?->toCents(),
            'amount_display' => $this->amount_cents?->format(),
            'balance_after' => $this->balance_after_cents?->toCents(),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
