<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Models\VehicleAllocation;
use Illuminate\Validation\Rule;

class VehicleAllocationRequest extends RegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->presence();

        return [
            'vehicle_id' => [
                $required,
                'integer',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
            ],
            'driver_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('drivers', 'id')->whereNull('deleted_at'),
            ],
            'adm_request_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('adm_requests', 'id')->whereNull('deleted_at'),
            ],
            'start_at' => [$required, 'date'],
            'end_at' => array_filter([$required, 'date', $this->has('start_at') ? 'after:start_at' : null]),
            'status' => ['sometimes', Rule::in(VehicleAllocation::STATUSES)],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:255'],
            'destination' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->blankToNull(['driver_id', 'adm_request_id', 'purpose', 'destination', 'notes']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['end_at.after' => 'The end must be after the start.'];
    }
}
