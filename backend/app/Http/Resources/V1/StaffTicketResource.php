<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Ticket;
use App\Services\StaffTicketQueryService;
use App\Services\TicketTimelineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * IT service-desk ticket payload, including internal notes and transitions.
 *
 * @mixin Ticket
 */
class StaffTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $includeWorkspace = $request->routeIs(
            'api.v1.staff.tickets.show',
            'api.v1.staff.tickets.assign',
            'api.v1.staff.tickets.status',
            'api.v1.staff.tickets.priority',
            'api.v1.staff.tickets.resolve',
            'api.v1.staff.tickets.clear-resolution',
            'api.v1.staff.tickets.close-as-verified',
        );

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'description' => $this->when($includeWorkspace, $this->description),
            'contact_location' => $this->contact_location,
            'preferred_contact_method' => $this->preferred_contact_method,
            'contact_phone' => $this->contact_phone,
            'additional_details' => $this->when($includeWorkspace, $this->additional_details),
            'issue_type' => $this->issue_type,
            'issue_type_label' => $this->lookupLabel('issue_types', $this->issue_type),
            'item_type' => $this->item_type,
            'item_type_label' => $this->lookupLabel('item_types', $this->item_type),
            'item_other' => $this->when($includeWorkspace, $this->item_other),
            'resolution_summary' => $this->when($includeWorkspace, $this->resolution_summary),
            // Always expose so list views can show resolve time without a detail fetch.
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->when(
                $includeWorkspace && $this->relationLoaded('resolver'),
                fn () => $this->resolver ? new UserSummaryResource($this->resolver) : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'awaiting_verification' => $this->whenLoaded(
                'status',
                fn () => (bool) (
                    $this->status?->is_resolved
                    && $this->status?->requires_verification
                    && ! $this->status?->is_closed
                ),
            ),
            'verification' => $this->when($includeWorkspace, function () use ($request) {
                $payload = (new TicketVerificationResource($this->resource))->toArray($request);

                return $payload === [] ? null : $payload;
            }),
            'category' => new TicketCategoryResource($this->whenLoaded('category')),
            'priority' => new TicketPriorityResource($this->whenLoaded('priority')),
            'status' => new TicketStatusResource($this->whenLoaded('status')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'assigned_to_team' => $this->isAssignedToTeam(),
            'assignee' => $this->whenLoaded(
                'assignee',
                fn () => $this->assignee ? new UserSummaryResource($this->assignee) : null,
            ),
            'assignees' => $this->when(
                $this->relationLoaded('assignments'),
                fn () => UserSummaryResource::collection(
                    $this->assignments
                        ->whereNull('released_at')
                        ->pluck('assignee')
                        ->filter()
                        ->unique('id')
                        ->values(),
                ),
            ),
            'requester' => $this->whenLoaded(
                'requester',
                fn () => $this->requester ? new UserSummaryResource($this->requester) : null,
            ),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => StaffTicketCommentResource::collection($this->whenLoaded('comments')),
            'timeline' => $this->when(
                $includeWorkspace,
                fn () => app(TicketTimelineService::class)->for($this->resource),
            ),
            'allowed_transitions' => $this->when(
                $includeWorkspace,
                fn () => app(StaffTicketQueryService::class)->allowedTransitions($this->resource, $request->user()),
            ),
        ];
    }

    private function lookupLabel(string $configKey, mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $options = config('helpdesk.'.$configKey, []);

        foreach ($options as $option) {
            if (($option['value'] ?? null) === $value) {
                return $option['label'] ?? $value;
            }
        }

        return $value;
    }
}
