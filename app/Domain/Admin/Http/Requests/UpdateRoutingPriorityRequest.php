<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutingPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'priorities' => ['required', 'array', 'min:1'],
            'priorities.*.id' => ['required', 'integer'],
            'priorities.*.priority' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'priorities.required' => 'Daftar urutan prioritas routing wajib dikirim.',
            'priorities.*.id.required' => 'ID target routing wajib ada.',
            'priorities.*.priority.required' => 'Nilai prioritas wajib diisi.',
            'priorities.*.priority.min' => 'Nilai prioritas minimal 1.',
        ];
    }
}
