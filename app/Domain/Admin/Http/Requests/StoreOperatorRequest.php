<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:operators,code'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'icon_url' => ['nullable', 'string', 'max:255'],
            'prefixes' => ['nullable'], // Format: string dipisah koma/newline atau array
            'prefix_rules' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
