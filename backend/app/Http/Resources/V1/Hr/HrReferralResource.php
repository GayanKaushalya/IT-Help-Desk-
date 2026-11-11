<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrDepartmentReferral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrDepartmentReferral */
class HrReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hr_request_id' => $this->hr_request_id,
            'department_slug' => $this->department_slug,
            'status' => $this->status,
            'brief' => $this->brief,
            'response' => $this->response,
            'created_at' => $this->created_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? new HrUserResource($this->creator) : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new HrUserResource($this->assignee) : null),
            'responder' => $this->whenLoaded('responder', fn () => $this->responder ? new HrUserResource($this->responder) : null),
        ];
    }
}
