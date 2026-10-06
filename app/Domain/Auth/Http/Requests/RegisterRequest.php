<?php

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+62[0-9]{9,13}$/'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Format nomor telepon tidak valid. Gunakan format E.164 (contoh: +6281234567890)',
        ];
    }
}
