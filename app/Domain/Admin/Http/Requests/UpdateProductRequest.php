<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $productId = $this->route('product') instanceof Product
            ? $this->route('product')->id
            : $this->route('product');

        return [
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'sku_code' => ['required', 'string', 'max:50', Rule::unique('products', 'sku_code')->ignore($productId)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'product_type' => ['required', 'string', Rule::in([Product::TYPE_PREPAID, Product::TYPE_POSTPAID])],
            'base_price_rupiah' => ['required', 'numeric', 'min:0'],
            'admin_fee_rupiah' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'allow_failover' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider_id.required' => 'Provider upstream wajib dipilih.',
            'category_id.required' => 'Kategori produk wajib dipilih.',
            'sku_code.required' => 'Kode SKU produk wajib diisi.',
            'sku_code.unique' => 'Kode SKU sudah terdaftar pada produk lain.',
            'name.required' => 'Nama produk wajib diisi.',
            'base_price_rupiah.required' => 'Harga modal provider wajib diisi.',
        ];
    }
}
