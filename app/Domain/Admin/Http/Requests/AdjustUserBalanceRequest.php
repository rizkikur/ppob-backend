<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustUserBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Pilih jenis penyesuaian (Credit / Debit).',
            'amount.required' => 'Nominal penyesuaian saldo wajib diisi.',
            'amount.min' => 'Nominal penyesuaian minimal Rp 1.',
            'note.required' => 'Alasan/catatan penyesuaian saldo wajib diisi demi kepatuhan audit.',
            'note.min' => 'Alasan minimal 5 karakter.',
        ];
    }
}
