<?php

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request logout.
 * Route: POST /auth/logout (requires auth:sanctum)
 */
class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
