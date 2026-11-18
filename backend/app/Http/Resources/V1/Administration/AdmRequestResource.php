<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee-facing Administration request payload. Internal notes and status
 * remarks are excluded; AdmStaffRequestResource adds them for Administration staff.
 *
 * @mixin AdmRequest
 */
class AdmRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs(
            'api.v1.administration.requests.show',
            'api.v1.administration.requests.store',
            'api.v1.administration.requests.track',
            'api.v1.administration.requests.verify',
            'api.v1.administration.staff.requests.show',
            'api.v1.administration.staff.requests.assign',
            'api.v1.administration.staff.requests.status',
            'api.v1.administration.staff.requests.resolve',
            'api.v1.administration.staff.requests.close-as-verified',
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
            'subject' => $this->subject,
            'description' => $this->when($detail, $this->description),
            'required_date' => $this->required_date?->toDateString(),
            'company_name' => $this->company_name,
            'details' => $this->when($detail, $this->details),
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

            'category' => new AdmCategoryResource($this->whenLoaded('category')),
            'request_type' => new AdmRequestTypeResource($this->whenLoaded('requestType')),
            'status' => new AdmStatusResource($this->whenLoaded('status')),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new AdmUserResource($this->assignee) : null),
            'resolved_by' => $this->when(
                $detail && $this->relationLoaded('resolver'),
                fn () => $this->resolver ? new AdmUserResource($this->resolver) : null,
            ),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester ? new AdmUserResource($this->requester) : null),
            'attachments' => AdmAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => AdmCommentResource::collection($this->whenLoaded('comments')),
            'timeline' => AdmStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
        ];
    }
}
