<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualStatusOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    Transaction::STATUS_PENDING,
                    Transaction::STATUS_PROCESSING,
                    Transaction::STATUS_SUCCESS,
                    Transaction::STATUS_FAILED,
                    Transaction::STATUS_REFUNDED,
                ]),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status baru wajib dipilih.',
            'reason.required' => 'Alasan status override wajib diisi.',
            'reason.min' => 'Alasan override minimal 10 karakter.',
        ];
    }
}
