<?php

namespace App\Domain\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PinVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string'],
            'pin' => ['required', 'string', 'digits:6'],
        ];
    }
}
