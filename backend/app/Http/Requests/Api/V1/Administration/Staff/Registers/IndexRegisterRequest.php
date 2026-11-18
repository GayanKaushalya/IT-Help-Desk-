<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\ApiFormRequest;

class IndexRegisterRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('adm.registers.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'renewal_status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'insurance_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:150'],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer'],
            'driver_id' => ['sometimes', 'nullable', 'integer'],
            'responsible_officer_id' => ['sometimes', 'nullable', 'integer'],
            'expired' => ['sometimes', 'boolean'],
            'expiring_within' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->validated();
    }
}
