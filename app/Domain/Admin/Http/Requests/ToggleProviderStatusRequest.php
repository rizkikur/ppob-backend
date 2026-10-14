<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleProviderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.required' => 'Status aktif provider wajib ditentukan.',
            'reason.required' => 'Alasan perubahan status provider wajib diisi.',
            'reason.min' => 'Alasan perubahan status minimal 10 karakter.',
        ];
    }
}
