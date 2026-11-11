<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequestStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrRequestStatusHistory */
class HrStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => new HrStatusResource($this->whenLoaded('fromStatus')),
            'to_status' => new HrStatusResource($this->whenLoaded('toStatus')),
            'remarks' => $this->when($request->routeIs('api.v1.hr.staff.*'), $this->remarks),
            'changed_at' => $this->changed_at?->toIso8601String(),
            'changed_by' => $this->when(
                $request->routeIs('api.v1.hr.staff.*') && $this->relationLoaded('changer'),
                fn () => $this->changer ? new HrUserResource($this->changer) : null,
            ),
        ];
    }
}
