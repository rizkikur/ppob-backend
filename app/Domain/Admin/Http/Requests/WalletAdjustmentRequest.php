<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && (auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->isFinance());
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'in:credit,debit,CREDIT,DEBIT'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih pengguna atau mitra yang dituju.',
            'user_id.exists' => 'Akun pengguna tidak ditemukan.',
            'type.required' => 'Jenis penyesuaian (Credit/Debit) wajib dipilih.',
            'amount.required' => 'Nominal penyesuaian saldo wajib diisi.',
            'amount.min' => 'Nominal minimal Rp 1.',
            'reason.required' => 'Alasan penyesuaian wajib diisi demi kepatuhan audit finansial.',
            'reason.min' => 'Alasan penyesuaian saldo harus spesifik (minimal 15 karakter).',
        ];
    }
}
