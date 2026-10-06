<?php

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+62[0-9]{9,13}$/'],
            'code' => ['required', 'string', 'digits:6'],
            'type' => ['required', 'string', Rule::in(['register', 'login'])],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
