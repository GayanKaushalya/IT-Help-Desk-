<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmRequestAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdmRequestAssignment */
class AdmAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new AdmUserResource($this->assignee) : null),
            'assigned_by' => $this->whenLoaded('assigner', fn () => $this->assigner ? new AdmUserResource($this->assigner) : null),
        ];
    }
}
