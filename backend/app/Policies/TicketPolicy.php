<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Authorization rules for tickets.
 *
 * Requesters may only reach their own tickets; IT staff are gated on the
 * permission matrix. The ticket module will build on these checks rather than
 * re-implementing ownership logic in controllers.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view_any') || $user->hasPermission('tickets.view_own');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($this->owns($user, $ticket)) {
            return true;
        }

        return $user->hasPermission('tickets.view_any') || $this->isAssignedTo($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $user->hasPermission('tickets.comment');
    }

    public function attach(User $user, Ticket $ticket): bool
    {
        $ticket->loadMissing('status');

        return $this->comment($user, $ticket) && ! $ticket->status?->is_closed;
    }

    /** Internal notes are never visible to the person who raised the ticket. */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $user->hasPermission('tickets.comment_internal');
    }

    public function updatePriority(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.assign') || $this->updateStatus($user, $ticket);
    }

    public function assign(User $user, Ticket $ticket, ?User $assignee = null): bool
    {
        if ($user->hasPermission('tickets.assign')) {
            return true;
        }

        $takingOwn = $assignee !== null && (int) $assignee->id === (int) $user->id;

        return $takingOwn && (
            $user->hasPermission('tickets.update_status')
            || $user->hasPermission('tickets.resolve')
        );
    }

    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.update_status') || $this->isAssignedTo($user, $ticket);
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.resolve') || $this->isAssignedTo($user, $ticket);
    }

    /** Staff may close as verified when the employee cannot confirm. */
    public function closeAsVerified(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.close')
            || $user->hasPermission('tickets.resolve')
            || $this->isAssignedTo($user, $ticket);
    }

    /** Only the requester confirms that their own issue is fixed. */
    public function verify(User $user, Ticket $ticket): bool
    {
        return $this->owns($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $this->owns($user, $ticket) || $user->hasPermission('tickets.close');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.delete');
    }

    private function owns(User $user, Ticket $ticket): bool
    {
        return (int) $ticket->requester_id === (int) $user->id;
    }

    private function isAssignedTo(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to !== null
            && (int) $ticket->assigned_to === (int) $user->id;
    }
}
