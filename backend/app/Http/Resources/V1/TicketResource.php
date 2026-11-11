<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Ticket;
use App\Services\TicketTimelineService;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Ticket payload used by the employee dashboard and ticket summary view.
 *
 * Password-bearing user records are never nested here — assignees are
 * reduced to a name so a list cannot leak account credentials.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'description' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.store'),
                $this->description,
            ),
            'contact_location' => $this->optionalAttribute('contact_location'),
            'preferred_contact_method' => $this->optionalAttribute('preferred_contact_method'),
            'contact_phone' => $this->optionalAttribute('contact_phone'),
            'additional_details' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.store'),
                $this->optionalAttribute('additional_details'),
            ),
            'issue_type' => $this->optionalAttribute('issue_type'),
            'issue_type_label' => $this->lookupLabel('issue_types', $this->optionalAttribute('issue_type')),
            'item_type' => $this->optionalAttribute('item_type'),
            'item_type_label' => $this->lookupLabel('item_types', $this->optionalAttribute('item_type')),
            'item_other' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.store'),
                $this->optionalAttribute('item_other'),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'resolution_summary' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.verify'),
                $this->optionalAttribute('resolution_summary'),
            ),
            'resolved_at' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.verify'),
                $this->optionalDate('resolved_at'),
            ),
            'resolved_by' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.verify') && $this->relationLoaded('resolver'),
                fn () => $this->resolver ? new UserSummaryResource($this->resolver) : null,
            ),
            'awaiting_verification' => $this->whenLoaded(
                'status',
                fn () => (bool) (
                    $this->status?->is_resolved
                    && $this->status?->requires_verification
                    && ! $this->status?->is_closed
                ),
            ),
            'verification' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.verify'),
                fn () => $this->verificationPayload($request),
            ),

            'category' => new TicketCategoryResource($this->whenLoaded('category')),
            'priority' => new TicketPriorityResource($this->whenLoaded('priority')),
            'status' => new TicketStatusResource($this->whenLoaded('status')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'assigned_to_team' => $this->isAssignedToTeam(),
            'assignee' => $this->whenLoaded(
                'assignee',
                fn () => $this->assignee ? new UserSummaryResource($this->assignee) : null,
            ),
            'requester' => $this->whenLoaded(
                'requester',
                fn () => $this->requester ? new UserSummaryResource($this->requester) : null,
            ),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'timeline' => $this->when(
                $request->routeIs('api.v1.tickets.show', 'api.v1.tickets.verify'),
                fn () => app(TicketTimelineService::class)->for($this->resource),
            ),
        ];
    }

    /**
     * SQL Server connections opened before a migration can omit new columns
     * from SELECT * metadata. Missing attributes must not 500 the dashboard.
     */
    private function optionalAttribute(string $key): mixed
    {
        $model = $this->resource;

        if (! $model instanceof Ticket || ! $model->offsetExists($key)) {
            return null;
        }

        return $model->getAttribute($key);
    }

    private function optionalDate(string $key): ?string
    {
        $value = $this->optionalAttribute($key);

        return $value instanceof DateTimeInterface
            ? Carbon::parse($value)->toIso8601String()
            : (is_string($value) && $value !== '' ? $value : null);
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

    /**
     * @return array<string, mixed>|null
     */
    private function verificationPayload(Request $request): ?array
    {
        $payload = (new TicketVerificationResource($this->resource))->toArray($request);

        return $payload === [] ? null : $payload;
    }
}
