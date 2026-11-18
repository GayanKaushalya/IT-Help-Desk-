<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\VehicleAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleAllocation
 */
class VehicleAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'adm_request_id' => $this->adm_request_id,
            'start_at' => $this->start_at?->format('Y-m-d\TH:i'),
            'end_at' => $this->end_at?->format('Y-m-d\TH:i'),
            'status' => $this->status,
            'purpose' => $this->purpose,
            'destination' => $this->destination,
            'notes' => $this->notes,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'full_name' => $this->driver->full_name,
            ] : null),
            'request' => $this->whenLoaded('admRequest', fn () => $this->admRequest ? [
                'id' => $this->admRequest->id,
                'reference' => $this->admRequest->reference,
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
