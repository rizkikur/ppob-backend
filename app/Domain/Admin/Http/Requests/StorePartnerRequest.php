<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'rate_limit_rpm' => ['required', 'integer', 'min:10', 'max:1000'],
            'protocol' => ['required', 'in:json,otomax,irs'],
            'response_mode' => ['required', 'in:sync,async'],
            'response_timeout_ms' => ['required', 'integer', 'min:1000', 'max:30000'],
            'callback_url' => ['nullable', 'url', 'max:500'],
            'allowed_ips' => ['nullable', 'string'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama mitra/perusahaan wajib diisi.',
            'email.unique' => 'Email kontak sudah digunakan oleh akun lain.',
            'callback_url.url' => 'Format URL callback tidak valid.',
            'rate_limit_rpm.required' => 'Batas rate limit RPM wajib ditentukan.',
        ];
    }
}
