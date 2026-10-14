<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    Transaction::STATUS_PENDING,
                    Transaction::STATUS_PROCESSING,
                    Transaction::STATUS_SUCCESS,
                    Transaction::STATUS_FAILED,
                    Transaction::STATUS_REFUNDED,
                ]),
            ],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'is_failover' => ['nullable', 'boolean'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'export' => ['nullable', 'string', Rule::in(['csv'])],
            'live' => ['nullable'],
        ];
    }

    /**
     * Sanitasi input sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('q') && is_string($this->q)) {
            $this->merge([
                'q' => strip_tags(trim($this->q)),
            ]);
        }
    }

    /**
     * Pesan validasi kustom dalam bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'date_start.date' => 'Format tanggal awal tidak valid.',
            'date_end.date' => 'Format tanggal akhir tidak valid.',
            'date_end.after_or_equal' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.',
            'q.max' => 'Kata kunci pencarian maksimal 100 karakter.',
        ];
    }
}
