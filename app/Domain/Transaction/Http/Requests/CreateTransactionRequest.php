<?php

namespace App\Domain\Transaction\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku_code' => ['required', 'string', 'max:50'],
            'customer_number' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\-]+$/'],
            'inquiry_id' => ['sometimes', 'nullable', 'integer', 'exists:inquiries,id'],
            'amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
