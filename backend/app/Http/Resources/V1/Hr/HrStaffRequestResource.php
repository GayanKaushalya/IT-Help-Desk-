<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequest;
use Illuminate\Http\Request;

/**
 * HR staff payload: the employee view plus assignment history and referrals.
 *
 * @mixin HrRequest
 */
class HrStaffRequestResource extends HrRequestResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'escalated_to' => $this->when(
                $this->relationLoaded('escalatedTo'),
                fn () => $this->escalatedTo ? new HrUserResource($this->escalatedTo) : null,
            ),
            'assignments' => HrAssignmentResource::collection($this->whenLoaded('assignments')),
            'referrals' => HrReferralResource::collection($this->whenLoaded('referrals')),
        ]);
    }
}
