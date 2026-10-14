<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && (auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->isFinance());
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
