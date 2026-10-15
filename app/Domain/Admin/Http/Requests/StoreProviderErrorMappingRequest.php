<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Ppob\Services\ProviderErrorMappingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderErrorMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        $validInternalCodes = array_keys(ProviderErrorMappingService::getStandardErrorDefinitions());

        return [
            'upstream_code' => ['required', 'string', 'max:50'],
            'internal_code' => ['required', 'string', Rule::in($validInternalCodes)],
            'custom_message' => ['nullable', 'string', 'max:255'],
            'is_retryable' => ['nullable', 'boolean'],
        ];
    }
}
