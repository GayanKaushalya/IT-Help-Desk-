<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\FuelCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only the internal card id and the masked reference are exposed.
 *
 * @mixin FuelCard
 */
class FuelCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'card_number' => $this->card_number,
            'masked_card_ref' => $this->masked_card_ref,
            'provider' => $this->provider,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'assigned_user_id' => $this->assigned_user_id,
            'monthly_limit' => $this->monthly_limit,
            'issued_at' => $this->issued_at?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'full_name' => $this->driver->full_name,
            ] : null),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id' => $this->assignedUser->id,
                'full_name' => $this->assignedUser->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
