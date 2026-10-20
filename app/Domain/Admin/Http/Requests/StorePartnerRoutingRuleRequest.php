<?php

namespace App\Domain\Admin\Http\Requests;

use App\Domain\Partner\Models\PartnerRoutingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRoutingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'category_code' => ['nullable', 'string', 'max:50'],
            'preferred_provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'allow_failover' => ['nullable', 'boolean'],
            'failover_policy' => ['required', 'string', Rule::in([
                PartnerRoutingRule::POLICY_NONE,
                PartnerRoutingRule::POLICY_SAME_CATEGORY,
                PartnerRoutingRule::POLICY_ANY,
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'failover_policy.required' => 'Kebijakan failover wajib dipilih.',
            'preferred_provider_id.exists' => 'Provider yang dipilih tidak terdaftar di sistem.',
        ];
    }
}
