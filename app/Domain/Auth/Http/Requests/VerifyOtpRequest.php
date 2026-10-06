<?php

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request verifikasi OTP.
 * Route: POST /auth/verify-register atau POST /auth/login
 */
class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
