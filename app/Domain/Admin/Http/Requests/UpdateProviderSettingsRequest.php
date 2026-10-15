<?php

namespace App\Domain\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'callback_slug' => ['nullable', 'string', 'max:50', Rule::unique('providers', 'callback_slug')->ignore($providerId)],
            'driver' => ['required', 'string', 'max:150'],
            'queue_name' => ['nullable', 'string', 'max:50'],
            'max_workers' => ['required', 'integer', 'min:1', 'max:100'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:300'],
            'priority' => ['required', 'integer', 'min:1', 'max:255'],
            'supports_balance_api' => ['nullable', 'boolean'],
            'balance_alert_threshold' => ['nullable', 'numeric', 'min:0'],
            'supports_callback' => ['nullable', 'boolean'],
            'supports_check_status' => ['nullable', 'boolean'],
            // Credentials
            'base_url' => ['nullable', 'url', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
