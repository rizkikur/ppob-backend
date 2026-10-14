<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan refund wajib diisi.',
            'reason.min' => 'Alasan refund minimal 10 karakter (cantumkan no tiket/alasan operasional).',
            'reason.max' => 'Alasan refund maksimal 255 karakter.',
        ];
    }
}
