<?php

namespace App\Domain\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_pin' => ['required', 'string', 'digits:6'],
            'new_pin_confirmation' => ['required', 'same:new_pin'],
        ];
    }
}
