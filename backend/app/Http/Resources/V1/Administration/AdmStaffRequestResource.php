<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmRequest;
use Illuminate\Http\Request;

/**
 * Administration staff payload: the employee view plus assignment history.
 *
 * @mixin AdmRequest
 */
class AdmStaffRequestResource extends AdmRequestResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'assignments' => AdmAssignmentResource::collection($this->whenLoaded('assignments')),
            'cctv_approval' => $this->whenLoaded('cctvApproval', fn () => $this->cctvApproval ? [
                'id' => $this->cctvApproval->id,
                'status' => $this->cctvApproval->status,
                'decided_at' => $this->cctvApproval->decided_at?->toIso8601String(),
                'decision_notes' => $this->cctvApproval->decision_notes,
                'decided_by' => $this->cctvApproval->relationLoaded('decider') && $this->cctvApproval->decider
                    ? ['id' => $this->cctvApproval->decider->id, 'full_name' => $this->cctvApproval->decider->full_name]
                    : null,
            ] : null),
        ]);
    }
}
