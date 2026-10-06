<?php

namespace App\Domain\Wallet\Http\Resources;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->balance_cents?->toCents() ?? 0,
            'balance_display' => $this->balance_cents?->format() ?? 'Rp 0,00',
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
