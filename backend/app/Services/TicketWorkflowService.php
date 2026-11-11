<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleSlug;
use App\Enums\VerificationStatus;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assignment, status, priority and resolution changes for the IT desk.
 */
class TicketWorkflowService
{
    public function __construct(
        private readonly TicketStatusTransition $transitions,
        private readonly AuditLogger $audit,
        private readonly TicketVerificationService $verifications,
        private readonly NotificationService $notifications,
    ) {}

    public function assign(Ticket $ticket, User $actor, User $assignee, ?string $note = null): Ticket
    {
        if (! $assignee->isStaff()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Tickets can only be assigned to IT staff.'],
            ]);
        }

        $ticket = DB::transaction(function () use ($ticket, $actor, $assignee, $note) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing('status');

            $previous = $ticket->assigned_to;

            $ticket->assignments()->current()->update(['released_at' => now()]);

            $ticket->assignments()->create([
                'assigned_to' => $assignee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'note' => $note,
            ]);

            $ticket->forceFill([
                'assigned_to' => $assignee->id,
                'assigned_at' => now(),
            ]);

            if ($ticket->status?->slug === 'new') {
                $assigned = TicketStatus::query()->where('slug', 'assigned')->first();
                if ($assigned) {
                    $ticket->status_id = $assigned->id;
                }
            }

            $ticket->save();

            $this->audit->log(
                event: 'ticket.assigned',
                subject: $ticket,
                oldValues: ['assigned_to' => $previous],
                newValues: ['assigned_to' => $assignee->id],
                description: $actor->username.' assigned '.$ticket->reference.' to '.$assignee->username,
            );

            return $ticket->fresh() ?? $ticket;
        });

        $this->notifications->ticketAssigned($ticket, $actor, $assignee);

        return $ticket;
    }

    /**
     * Put every active IT staff member on the ticket so the desk can work it
     * without a separate take/assign step. The primary owner is an admin when
     * one exists; notifications stay on ticket.created / ticket.new.
     */
    public function assignToAllStaff(Ticket $ticket, User $actor): Ticket
    {
        $staff = User::query()->staff()->active()->orderBy('id')->get();

        if ($staff->isEmpty()) {
            return $ticket;
        }

        $primary = $staff->first(
            fn (User $member): bool => $member->hasRole(RoleSlug::Administrator, RoleSlug::SuperAdmin),
        ) ?? $staff->first();

        return DB::transaction(function () use ($ticket, $actor, $staff, $primary) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing('status');

            $ticket->assignments()->current()->update(['released_at' => now()]);

            foreach ($staff as $member) {
                $ticket->assignments()->create([
                    'assigned_to' => $member->id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'note' => 'Automatically assigned to the IT desk.',
                ]);
            }

            $ticket->forceFill([
                'assigned_to' => $primary->id,
                'assigned_at' => now(),
            ]);

            if ($ticket->status?->slug === 'new') {
                $assigned = TicketStatus::query()->where('slug', 'assigned')->first();
                if ($assigned) {
                    $ticket->status_id = $assigned->id;
                }
            }

            $ticket->save();

            $this->audit->log(
                event: 'ticket.assigned',
                subject: $ticket,
                newValues: [
                    'assigned_to' => $primary->id,
                    'assigned_to_team' => true,
                ],
                description: $actor->username.' assigned '.$ticket->reference.' to all IT staff',
            );

            return $ticket->fresh() ?? $ticket;
        });
    }

    public function assignOpenTicketsToStaffTeam(): int
    {
        $staff = User::query()->staff()->active()->orderBy('id')->first();

        if (! $staff) {
            return 0;
        }

        $updated = 0;

        Ticket::query()
            ->open()
            ->with('requester')
            ->orderBy('id')
            ->each(function (Ticket $ticket) use ($staff, &$updated): void {
                $actor = $ticket->requester ?? $staff;
                $this->assignToAllStaff($ticket, $actor);
                $updated++;
            });

        return $updated;
    }

    /**
     * @return array{ticket: Ticket, verification_code: string|null}
     */
    public function changeStatus(Ticket $ticket, User $actor, TicketStatus $target, ?string $resolutionSummary = null): array
    {
        if ($target->slug === 'closed') {
            throw ValidationException::withMessages([
                'status_id' => ['Tickets can only be closed after the employee verifies the resolution.'],
            ]);
        }

        if ($target->slug === 'resolved') {
            return $this->resolve($ticket, $actor, (string) $resolutionSummary);
        }

        if (in_array($target->slug, ['assigned', 'in-progress', 'on-hold', 'awaiting-user'], true)) {
            $ticket = $this->claimIfUnassigned($ticket, $actor);

            if ($target->slug === 'assigned') {
                return [
                    'ticket' => $ticket,
                    'verification_code' => null,
                ];
            }
        }

        $result = DB::transaction(function () use ($ticket, $actor, $target) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing('status');

            $from = $ticket->status?->slug ?? '';

            if (! $this->transitions->allows($from, $target->slug)) {
                throw ValidationException::withMessages([
                    'status_id' => ['This ticket cannot move from '.$ticket->status?->name.' to '.$target->name.'.'],
                ]);
            }

            $this->applyStatusSideEffects($ticket, $actor, $target);
            $ticket->status_id = $target->id;
            $ticket->save();

            $this->audit->log(
                event: 'ticket.status_changed',
                subject: $ticket,
                oldValues: ['status' => $from],
                newValues: ['status' => $target->slug],
                description: $actor->username.' moved '.$ticket->reference.' to '.$target->name,
            );

            return [
                'ticket' => $ticket->fresh() ?? $ticket,
                'verification_code' => null,
            ];
        });

        if ($target->slug === 'awaiting-user') {
            $this->notifications->informationRequested($result['ticket'], $actor);
        }

        return $result;
    }

    public function changePriority(Ticket $ticket, User $actor, TicketPriority $priority): Ticket
    {
        $previous = $ticket->priority_id;
        $ticket->priority_id = $priority->id;
        $ticket->save();

        $this->audit->log(
            event: 'ticket.priority_changed',
            subject: $ticket,
            oldValues: ['priority_id' => $previous],
            newValues: ['priority_id' => $priority->id],
            description: $actor->username.' changed priority on '.$ticket->reference,
        );

        return $ticket->fresh() ?? $ticket;
    }

    /**
     * @return array{ticket: Ticket, verification_code: string|null}
     */
    public function resolve(Ticket $ticket, User $actor, string $summary): array
    {
        $summary = trim($summary);

        if ($summary === '') {
            throw ValidationException::withMessages([
                'resolution_summary' => ['Describe how the issue was resolved.'],
            ]);
        }

        $resolved = TicketStatus::query()->where('slug', 'resolved')->first();

        if (! $resolved) {
            throw ValidationException::withMessages([
                'status_id' => ['The resolved status has not been seeded.'],
            ]);
        }

        $ticket = $this->claimIfUnassigned($ticket, $actor);

        $result = DB::transaction(function () use ($ticket, $actor, $resolved, $summary) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing('status');

            $from = $ticket->status?->slug ?? '';

            if (! $this->transitions->allows($from, 'resolved')) {
                throw ValidationException::withMessages([
                    'status_id' => ['This ticket cannot be resolved from its current status.'],
                ]);
            }

            $ticket->forceFill([
                'status_id' => $resolved->id,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolution_summary' => $summary,
            ])->save();

            $code = $this->verifications->issue($ticket, $actor);

            $this->audit->log(
                event: 'ticket.resolved',
                subject: $ticket,
                newValues: ['status' => 'resolved'],
                description: $actor->username.' resolved '.$ticket->reference,
            );

            return [
                'ticket' => $ticket->fresh() ?? $ticket,
                'verification_code' => $code,
            ];
        });

        $this->notifications->ticketResolved($result['ticket'], $actor);
        $this->notifications->verificationRequired($result['ticket'], $actor);

        return $result;
    }

    /**
     * Close a ticket as if the employee confirmed the fix.
     * Staff use this when confirmation is not needed / unavailable.
     * Ticket and verification records attribute the close to the requester
     * so employee and staff UIs both show a normal verified close.
     */
    public function closeAsVerified(Ticket $ticket, User $actor): Ticket
    {
        $closed = TicketStatus::query()->where('slug', 'closed')->first();

        if (! $closed) {
            throw ValidationException::withMessages([
                'status_id' => ['The closed status has not been seeded.'],
            ]);
        }

        $updated = DB::transaction(function () use ($ticket, $actor, $closed) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing(['status', 'requester', 'verifications']);

            if ($ticket->status?->is_closed || $ticket->status?->slug === 'closed') {
                throw ValidationException::withMessages([
                    'status_id' => ['This ticket is already closed.'],
                ]);
            }

            $ticket->loadMissing('assignments');
            $ticket->assertIndividualAssigneeForClose();

            $awaiting = (bool) (
                $ticket->status?->slug === 'resolved'
                || (
                    $ticket->status?->is_resolved
                    && $ticket->status?->requires_verification
                    && ! $ticket->status?->is_closed
                )
            );

            if (! $awaiting && blank($ticket->resolution_summary)) {
                throw ValidationException::withMessages([
                    'status_id' => ['Resolve the ticket first, or add a resolution before closing as verified.'],
                ]);
            }

            $requesterId = (int) $ticket->requester_id;
            $now = now();

            $verification = $ticket->verifications()
                ->where('status', VerificationStatus::Pending->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $verification) {
                $verification = $ticket->verifications()->orderByDesc('id')->lockForUpdate()->first();
            }

            if ($verification) {
                $verification->forceFill([
                    'status' => VerificationStatus::Verified,
                    'verified_at' => $now,
                    'verified_by' => $requesterId,
                    'method' => 'employee_confirmation',
                    'blocked_until' => null,
                ])->save();
            } else {
                $ticket->verifications()->create([
                    'code_hash' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
                    'code_last_four' => null,
                    'code_ciphertext' => null,
                    'issued_by' => $actor->id,
                    'issued_to' => $requesterId,
                    'status' => VerificationStatus::Verified,
                    'method' => 'employee_confirmation',
                    'issued_at' => $ticket->resolved_at ?? $now,
                    'expires_at' => $now,
                    'verified_at' => $now,
                    'verified_by' => $requesterId,
                    'attempts' => 0,
                ]);
            }

            $ticket->forceFill([
                'status_id' => $closed->id,
                'resolved_at' => $ticket->resolved_at ?? $now,
                'resolved_by' => $ticket->resolved_by ?? $actor->id,
                'resolution_summary' => $ticket->resolution_summary ?: 'Resolved by IT support.',
                'verified_at' => $now,
                'closed_at' => $now,
                'closed_by' => $requesterId,
            ])->save();

            $this->audit->log(
                event: 'ticket.verified',
                subject: $ticket,
                newValues: [
                    'status' => 'closed',
                    'verified_by' => $requesterId,
                    'verification_method' => 'employee_confirmation',
                    'closed_on_behalf_by' => $actor->id,
                    'verified_at' => $now->toIso8601String(),
                ],
                description: $actor->username.' closed '.$ticket->reference.' as verified by the employee',
            );

            $this->audit->log(
                event: 'ticket.closed',
                subject: $ticket,
                newValues: ['status' => 'closed', 'closed_by' => $requesterId],
                description: $ticket->requester?->username
                    ? $ticket->requester->username.' closed '.$ticket->reference
                    : $ticket->reference.' was closed after verification',
            );

            return $ticket->fresh() ?? $ticket;
        });

        try {
            $this->notifications->ticketClosed($updated, $actor);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $updated;
    }

    /**
     * Undo a resolution: clear the summary, cancel pending confirmation,
     * and return the ticket to In Progress so IT can keep working.
     */
    public function clearResolution(Ticket $ticket, User $actor): Ticket
    {
        $inProgress = TicketStatus::query()->where('slug', 'in-progress')->first();

        if (! $inProgress) {
            throw ValidationException::withMessages([
                'status_id' => ['The in-progress status has not been seeded.'],
            ]);
        }

        $updated = DB::transaction(function () use ($ticket, $actor, $inProgress) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing('status');

            $slug = $ticket->status?->slug;
            $awaiting = (bool) (
                $ticket->status?->is_resolved
                && $ticket->status?->requires_verification
                && ! $ticket->status?->is_closed
            );

            if ($slug !== 'resolved' && ! $awaiting && blank($ticket->resolution_summary)) {
                throw ValidationException::withMessages([
                    'status_id' => ['This ticket has no resolution to clear.'],
                ]);
            }

            if ($ticket->status?->is_closed) {
                throw ValidationException::withMessages([
                    'status_id' => ['Closed tickets cannot have their resolution cleared. Reopen them first.'],
                ]);
            }

            $ticket->verifications()
                ->where('status', VerificationStatus::Pending->value)
                ->update(['status' => VerificationStatus::Cancelled->value]);

            $ticket->forceFill([
                'status_id' => $inProgress->id,
                'resolution_summary' => null,
                'resolved_at' => null,
                'resolved_by' => null,
                'verified_at' => null,
                'is_reopened' => true,
                'reopen_count' => (int) $ticket->reopen_count + 1,
            ])->save();

            $this->audit->log(
                event: 'ticket.resolution_cleared',
                subject: $ticket,
                newValues: ['status' => 'in-progress'],
                description: $actor->username.' cleared the resolution on '.$ticket->reference,
            );

            return $ticket->fresh() ?? $ticket;
        });

        return $updated;
    }

    private function claimIfUnassigned(Ticket $ticket, User $actor): Ticket
    {
        $ticket->loadMissing('assignments');

        if ($ticket->hasIndividualAssignee()) {
            return $ticket;
        }

        if (! $actor->isStaff()) {
            throw ValidationException::withMessages([
                'status_id' => ['Assign the ticket before changing its status.'],
            ]);
        }

        return $this->assign($ticket, $actor, $actor);
    }

    private function applyStatusSideEffects(Ticket $ticket, User $actor, TicketStatus $target): void
    {
        if ($target->slug === 'in-progress' && ! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
        }

        if ($target->slug === 'resolved') {
            $ticket->forceFill([
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
            ]);
        }
    }
}
