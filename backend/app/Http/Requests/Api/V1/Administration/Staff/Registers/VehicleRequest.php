<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class VehicleRequest extends RegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->presence();

        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'registration_number' => [
                $required,
                'string',
                'max:40',
                Rule::unique('vehicles', 'registration_number')->ignore($this->route('vehicle')),
            ],
            'make' => ['sometimes', 'nullable', 'string', 'max:80'],
            'model' => ['sometimes', 'nullable', 'string', 'max:80'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'fuel_type' => ['sometimes', 'nullable', 'string', 'max:40'],
            'chassis_number' => ['sometimes', 'nullable', 'string', 'max:60'],
            'current_mileage' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999'],
            'driver_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('drivers', 'id')->whereNull('deleted_at'),
            ],
            'insurance_policy_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('insurance_policies', 'id')->whereNull('deleted_at'),
            ],
            'revenue_license_expiry' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(Vehicle::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull([
            'company_name', 'make', 'model', 'year', 'vehicle_type', 'fuel_type', 'chassis_number',
            'current_mileage', 'driver_id', 'insurance_policy_id', 'revenue_license_expiry', 'notes',
        ]);

        if ($this->has('registration_number') && is_string($this->input('registration_number'))) {
            $this->merge(['registration_number' => strtoupper(trim($this->input('registration_number')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['registration_number.unique' => 'A vehicle with this registration number already exists.'];
    }
}
