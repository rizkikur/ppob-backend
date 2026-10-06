<?php

namespace App\Domain\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk response token setelah login/register berhasil.
 *
 * Digunakan dengan: new TokenResource(['token' => $token, 'user' => $user])
 * Atau: new TokenResource($result) di mana $result = ['token' => ..., 'user' => ...]
 */
class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($this->resource['user']),
        ];
    }
}
