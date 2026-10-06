<?php

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request pengiriman OTP.
 * Route: POST /auth/otp
 */
class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{8,12}$/'],
            'purpose' => ['required_without:type', 'nullable', 'string', 'in:register,login'],
            'type' => ['required_without:purpose', 'nullable', 'string', 'in:register,login,reset_pin'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Format nomor telepon tidak valid. Gunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.',
            'purpose.in' => 'Purpose harus salah satu dari: register, login.',
            'type.in' => 'Type harus salah satu dari: register, login, reset_pin.',
        ];
    }
}
