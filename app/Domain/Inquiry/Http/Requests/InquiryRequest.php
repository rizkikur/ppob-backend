<?php

namespace App\Domain\Inquiry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InquiryRequest extends FormRequest
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
        ];
    }
}
