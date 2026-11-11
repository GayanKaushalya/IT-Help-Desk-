<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee-facing HR request payload. Internal notes, referrals and status
 * remarks are excluded; HrStaffRequestResource adds them for HR staff.
 *
 * @mixin HrRequest
 */
class HrRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs(
            'api.v1.hr.requests.show',
            'api.v1.hr.requests.store',
            'api.v1.hr.requests.track',
            'api.v1.hr.requests.verify',
            'api.v1.hr.staff.requests.show',
            'api.v1.hr.staff.requests.assign',
            'api.v1.hr.staff.requests.status',
            'api.v1.hr.staff.requests.resolve',
            'api.v1.hr.staff.requests.close-as-verified',
            'api.v1.hr.staff.requests.refer',
        );

        $awaitingVerification = $this->relationLoaded('status')
            ? $this->isAwaitingVerification()
            : false;

        $verificationStatus = null;
        if ($this->status?->slug === 'closed' && $this->resolved_at !== null) {
            $verificationStatus = 'verified';
        } elseif ($awaitingVerification) {
            $verificationStatus = 'pending';
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee_name,
            'department_name' => $this->department_name,
            'designation' => $this->designation,
            'description' => $this->when($detail, $this->description),
            'is_confidential' => $this->is_confidential,
            'is_overdue' => $this->whenLoaded('status', fn () => $this->isOverdue()),
            'awaiting_verification' => $awaitingVerification,
            'assigned_to_team' => $this->isAssignedToTeam(),
            'due_at' => $this->due_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'resolution_summary' => $this->when($detail, $this->resolution_summary),
            'resolved_at' => $this->when($detail, $this->resolved_at?->toIso8601String()),
            'closed_at' => $this->when($detail, $this->closed_at?->toIso8601String()),
            'verification' => $this->when($detail || $awaitingVerification, [
                'status' => $verificationStatus,
                'completed_at' => $this->closed_at?->toIso8601String(),
            ]),

            'category' => new HrCategoryResource($this->whenLoaded('category')),
            'request_type' => new HrRequestTypeResource($this->whenLoaded('requestType')),
            'status' => new HrStatusResource($this->whenLoaded('status')),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new HrUserResource($this->assignee) : null),
            'resolved_by' => $this->when(
                $detail && $this->relationLoaded('resolver'),
                fn () => $this->resolver ? new HrUserResource($this->resolver) : null,
            ),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester ? new HrUserResource($this->requester) : null),
            'attachments' => HrAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => HrCommentResource::collection($this->whenLoaded('comments')),
            'timeline' => HrStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
        ];
    }
}
