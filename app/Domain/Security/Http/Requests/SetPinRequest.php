<?php

namespace App\Domain\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'digits:6'],
            'pin_confirmation' => ['required', 'same:pin'],
        ];
    }
}
