<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Models\InsurancePolicy;
use Illuminate\Validation\Rule;

class InsurancePolicyRequest extends RegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->presence();
        $policyId = $this->route('policy');

        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'insurance_type' => [$required, 'string', 'max:80'],
            'provider' => [$required, 'string', 'max:150'],
            'policy_number' => [
                $required,
                'string',
                'max:80',
                Rule::unique('insurance_policies', 'policy_number')->ignore($policyId),
            ],
            'insured_asset' => ['sometimes', 'nullable', 'string', 'max:200'],
            'vehicle_registration' => ['sometimes', 'nullable', 'string', 'max:40'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => [$required, 'date', 'after_or_equal:start_date'],
            'premium_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'sum_insured' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'responsible_officer_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'renewal_status' => ['sometimes', Rule::in(InsurancePolicy::RENEWAL_STATUSES)],
            'status' => ['sometimes', Rule::in(InsurancePolicy::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'company_name', 'insured_asset', 'vehicle_registration', 'start_date',
            'premium_amount', 'sum_insured', 'responsible_officer_id', 'notes',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy_number.unique' => 'A policy with this number already exists.',
            'expiry_date.after_or_equal' => 'The expiry date cannot be before the start date.',
        ];
    }
}
