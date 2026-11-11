<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Builds the employee-facing history of a ticket from lifecycle timestamps,
 * assignment rows and the current status. Internal IT notes never appear here.
 */
class TicketTimelineService
{
    /**
     * @return list<array{id: string, type: string, title: string, description: string|null, occurred_at: string|null}>
     */
    public function for(Ticket $ticket): array
    {
        $status = $ticket->status?->slug;
        $events = [];

        $events[] = $this->event(
            'created',
            'created',
            'Ticket Created',
            $ticket->created_at,
            $ticket->requester?->full_name
                ? $ticket->requester->full_name.' submitted this request.'
                : null,
        );

        $assignedAt = $ticket->assignments->sortBy('assigned_at')->first()?->assigned_at
            ?? $ticket->assigned_at;
        $assigneeName = $ticket->assignee?->full_name
            ?? $ticket->assignments->sortByDesc('assigned_at')->first()?->assignee?->full_name;

        if (
            $assignedAt
            || in_array($status, ['assigned', 'in-progress', 'on-hold', 'awaiting-user', 'resolved', 'closed'], true)
        ) {
            $events[] = $this->event(
                'assigned',
                'assigned',
                'Assigned to IT Support',
                $assignedAt ?? $ticket->created_at,
                $assigneeName ? 'Assigned to '.$assigneeName.'.' : null,
            );
        }

        $startedStatuses = ['in-progress', 'on-hold', 'awaiting-user', 'resolved', 'closed'];

        if ($ticket->first_responded_at || in_array($status, $startedStatuses, true)) {
            $events[] = $this->event(
                'in-progress',
                'in_progress',
                'IT Support Started Work',
                $ticket->first_responded_at ?? $assignedAt ?? $ticket->updated_at,
                null,
            );
        }

        if ($status === 'awaiting-user') {
            $events[] = $this->event(
                'awaiting-user',
                'awaiting_user',
                'IT requested information',
                $ticket->updated_at,
                'IT support is waiting for a reply from you.',
            );
        }

        if ($ticket->resolved_at || in_array($status, ['resolved', 'closed'], true)) {
            $events[] = $this->event(
                'resolved',
                'resolved',
                'IT resolved issue',
                $ticket->resolved_at ?? $ticket->updated_at,
                $this->attribute($ticket, 'resolution_summary'),
            );
        }

        if ($this->isAwaitingVerification($ticket)) {
            $events[] = $this->event(
                'awaiting-verification',
                'awaiting_verification',
                'Waiting for User Verification',
                $ticket->resolved_at ?? $ticket->updated_at,
                'Please confirm that the issue is fixed.',
            );
        }

        if ($ticket->verified_at) {
            $events[] = $this->event(
                'verified',
                'verified',
                'Resolution verified',
                $ticket->verified_at,
                null,
            );
        }

        if ($ticket->closed_at || $status === 'closed') {
            $events[] = $this->event(
                'closed',
                'closed',
                'Ticket closed',
                $ticket->closed_at ?? $ticket->updated_at,
                null,
            );
        }

        if ($status === 'cancelled') {
            $events[] = $this->event(
                'cancelled',
                'cancelled',
                'Ticket cancelled',
                $ticket->updated_at,
                null,
            );
        }

        usort($events, function (array $left, array $right): int {
            return strcmp((string) $left['occurred_at'], (string) $right['occurred_at']);
        });

        return $events;
    }

    /**
     * @return array{id: string, type: string, title: string, description: string|null, occurred_at: string|null}
     */
    private function event(
        string $id,
        string $type,
        string $title,
        mixed $occurredAt,
        ?string $description,
    ): array {
        $timestamp = $occurredAt instanceof Carbon
            ? $occurredAt->toIso8601String()
            : ($occurredAt ? Carbon::parse($occurredAt)->toIso8601String() : null);

        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'occurred_at' => $timestamp,
        ];
    }

    private function isAwaitingVerification(Ticket $ticket): bool
    {
        $status = $ticket->status;

        return $status !== null
            && $status->is_resolved
            && $status->requires_verification
            && ! $status->is_closed;
    }

    private function attribute(Ticket $ticket, string $key): ?string
    {
        if (! $ticket->offsetExists($key)) {
            return null;
        }

        $value = $ticket->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
