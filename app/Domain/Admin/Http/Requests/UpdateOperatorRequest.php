<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Product\Models\Operator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        $operatorId = $this->route('operator') instanceof Operator
            ? $this->route('operator')->id
            : $this->route('operator');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', Rule::unique('operators', 'code')->ignore($operatorId)],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'icon_url' => ['nullable', 'string', 'max:255'],
            'prefixes' => ['nullable'],
            'prefix_rules' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
