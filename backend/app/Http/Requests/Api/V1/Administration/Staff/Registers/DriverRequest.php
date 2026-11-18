<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Models\Driver;
use Illuminate\Validation\Rule;

class DriverRequest extends RegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'full_name' => [$this->presence(), 'string', 'min:2', 'max:150'],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'license_class' => ['sometimes', 'nullable', 'string', 'max:30'],
            'license_expiry_date' => ['sometimes', 'nullable', 'date'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'status' => ['sometimes', Rule::in(Driver::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'company_name', 'employee_id', 'license_number', 'license_class',
            'license_expiry_date', 'phone', 'email', 'notes',
        ]);
    }
}
