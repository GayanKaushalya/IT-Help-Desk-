<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdmRequest;
use App\Models\User;

/**
 * Authorization rules for Administration requests.
 *
 * Employees reach only their own requests. Administration staff are gated on
 * the permission matrix (adm.*) or on being the request's assignee.
 */
class AdmRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('adm.requests.view_own') || $user->hasPermission('adm.requests.view_any');
    }

    /** Access to the Administration staff queue and dashboard. */
    public function viewQueue(User $user): bool
    {
        return $user->hasPermission('adm.requests.view_any');
    }

    public function view(User $user, AdmRequest $request): bool
    {
        if ($this->owns($user, $request)) {
            return true;
        }

        if ($this->isAssignedTo($user, $request)) {
            return true;
        }

        return $user->hasPermission('adm.requests.view_any');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('adm.requests.create');
    }

    public function comment(User $user, AdmRequest $request): bool
    {
        if (! $this->view($user, $request) || ! $user->hasPermission('adm.requests.comment')) {
            return false;
        }

        // Employees cannot reopen a finished conversation; staff can still reply.
        if ($this->owns($user, $request) && ! $user->hasPermission('adm.requests.comment_internal')) {
            $request->loadMissing('status');

            return ! ($request->status?->is_terminal ?? false);
        }

        return true;
    }

    /** Internal notes are never visible to the employee. */
    public function addInternalNote(User $user, AdmRequest $request): bool
    {
        return $this->view($user, $request) && $user->hasPermission('adm.requests.comment_internal');
    }

    public function assign(User $user, AdmRequest $request, ?User $assignee = null): bool
    {
        if (! $this->view($user, $request)) {
            return false;
        }

        if ($user->hasPermission('adm.requests.assign')) {
            return true;
        }

        $takingOwn = $assignee !== null && (int) $assignee->id === (int) $user->id;

        return $takingOwn && (
            $user->hasPermission('adm.requests.update_status')
            || $user->hasPermission('adm.requests.resolve')
        );
    }

    public function updateStatus(User $user, AdmRequest $request): bool
    {
        return $this->view($user, $request)
            && ($user->hasPermission('adm.requests.update_status') || $this->isAssignedTo($user, $request))
            && ! $this->owns($user, $request);
    }

    public function resolve(User $user, AdmRequest $request): bool
    {
        return $this->view($user, $request)
            && ($user->hasPermission('adm.requests.resolve') || $this->isAssignedTo($user, $request))
            && ! $this->owns($user, $request);
    }

    /** Only the requester confirms that their own Administration request is resolved. */
    public function verify(User $user, AdmRequest $request): bool
    {
        return $this->owns($user, $request);
    }

    /** Staff may close as verified when the employee cannot confirm. */
    public function closeAsVerified(User $user, AdmRequest $request): bool
    {
        return $this->resolve($user, $request);
    }

    /**
     * CCTV approvers decide footage/surveillance requests. Nobody approves a
     * request they raised themselves.
     */
    public function decideCctv(User $user, AdmRequest $request): bool
    {
        return $request->isCctv()
            && $this->view($user, $request)
            && $user->hasPermission('adm.cctv.approve')
            && ! $this->owns($user, $request);
    }

    private function owns(User $user, AdmRequest $request): bool
    {
        return (int) $request->requester_id === (int) $user->id;
    }

    private function isAssignedTo(User $user, AdmRequest $request): bool
    {
        return $request->assigned_to !== null
            && (int) $request->assigned_to === (int) $user->id;
    }
}
