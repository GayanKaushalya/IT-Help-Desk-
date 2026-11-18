<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmRequestStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdmRequestStatusHistory */
class AdmStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => new AdmStatusResource($this->whenLoaded('fromStatus')),
            'to_status' => new AdmStatusResource($this->whenLoaded('toStatus')),
            'remarks' => $this->when($request->routeIs('api.v1.administration.staff.*'), $this->remarks),
            'changed_at' => $this->changed_at?->toIso8601String(),
            'changed_by' => $this->when(
                $request->routeIs('api.v1.administration.staff.*') && $this->relationLoaded('changer'),
                fn () => $this->changer ? new AdmUserResource($this->changer) : null,
            ),
        ];
    }
}
