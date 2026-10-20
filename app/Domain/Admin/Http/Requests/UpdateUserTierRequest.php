<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'user_tier_id' => ['required', 'integer', 'exists:user_tiers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_tier_id.required' => 'Pilih tier level pengguna.',
            'user_tier_id.exists' => 'Tier level yang dipilih tidak valid.',
        ];
    }
}
