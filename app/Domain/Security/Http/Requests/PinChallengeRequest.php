<?php

namespace App\Domain\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PinChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(['transaction', 'change_pin'])],
        ];
    }
}
