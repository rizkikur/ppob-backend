<?php

namespace App\Domain\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopupRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:100'],
            'method' => ['required', 'string', 'in:payment_gateway,manual_transfer'],
            'payment_gateway' => [
                'required_if:method,payment_gateway',
                'nullable',
                'string',
                'in:midtrans,xendit,fake',
            ],
        ];
    }
}
