<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate_limit_rpm' => ['required', 'integer', 'min:10', 'max:1000'],
            'protocol' => ['required', 'in:json,otomax,irs'],
            'response_mode' => ['required', 'in:sync,async'],
            'response_timeout_ms' => ['required', 'integer', 'min:1000', 'max:30000'],
            'callback_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama mitra wajib diisi.',
            'callback_url.url' => 'Format URL callback tidak valid.',
        ];
    }
}
