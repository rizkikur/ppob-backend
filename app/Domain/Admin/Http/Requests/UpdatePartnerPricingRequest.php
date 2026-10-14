<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'sell_price' => ['required', 'numeric', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Pilih produk yang ingin diatur harga khususnya.',
            'product_id.exists' => 'Produk tidak ditemukan.',
            'sell_price.required' => 'Harga jual khusus mitra wajib diisi.',
            'sell_price.min' => 'Harga jual minimal Rp 1.',
        ];
    }
}
