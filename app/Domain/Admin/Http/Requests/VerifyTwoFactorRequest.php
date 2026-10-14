<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode autentikasi 2FA wajib diisi.',
            'code.size' => 'Kode autentikasi harus terdiri dari 6 digit.',
            'code.regex' => 'Kode autentikasi hanya boleh berisi angka.',
        ];
    }
}
