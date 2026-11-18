<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'full_name' => $this->full_name,
            'employee_id' => $this->employee_id,
            'license_number' => $this->license_number,
            'license_class' => $this->license_class,
            'license_expiry_date' => $this->license_expiry_date?->toDateString(),
            'license_expired' => $this->license_expiry_date !== null && $this->license_expiry_date->endOfDay()->isPast(),
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
