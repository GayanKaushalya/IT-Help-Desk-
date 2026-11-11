<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReferHrRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $departments = collect(config('hrhelpdesk.referral_departments', []))->pluck('slug')->all();

        return [
            'department' => ['required', 'string', Rule::in($departments)],
            'brief' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'department.required' => 'Select a department to refer this request to.',
            'department.in' => 'The selected department is not available for referrals.',
        ];
    }
}
