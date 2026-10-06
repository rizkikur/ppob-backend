<?php

namespace App\Domain\Auth\Http\Resources;

use App\Domain\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier?->name),
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
