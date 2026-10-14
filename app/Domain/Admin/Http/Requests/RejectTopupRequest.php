<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && (auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->isFinance());
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan tiket top-up wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ];
    }
}
