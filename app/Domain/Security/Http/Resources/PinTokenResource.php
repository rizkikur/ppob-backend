<?php

namespace App\Domain\Security\Http\Resources;

use App\Domain\Security\Models\PinVerificationToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PinVerificationToken */
class PinTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pin_verification_token' => $this->token,
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
