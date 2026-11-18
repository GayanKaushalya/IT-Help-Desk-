<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'registration_number' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'vehicle_type' => $this->vehicle_type,
            'fuel_type' => $this->fuel_type,
            'chassis_number' => $this->chassis_number,
            'current_mileage' => $this->current_mileage,
            'driver_id' => $this->driver_id,
            'insurance_policy_id' => $this->insurance_policy_id,
            'revenue_license_expiry' => $this->revenue_license_expiry?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'full_name' => $this->driver->full_name,
            ] : null),
            'insurance_policy' => $this->whenLoaded('insurancePolicy', fn () => $this->insurancePolicy ? [
                'id' => $this->insurancePolicy->id,
                'policy_number' => $this->insurancePolicy->policy_number,
                'expiry_date' => $this->insurancePolicy->expiry_date?->toDateString(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
