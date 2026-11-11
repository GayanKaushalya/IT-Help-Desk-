<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HrDepartmentReferral;
use App\Models\HrRequest;
use App\Models\User;

/**
 * Authorization rules for HR requests.
 *
 * Employees reach only their own requests. HR staff are gated on the
 * permission matrix, and confidential complaints are further limited to the
 * assignee, managers, view_confidential holders and explicit complaint access.
 */
class HrRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.requests.view_own') || $user->hasPermission('hr.requests.view_any');
    }

    /** Access to the HR staff queue and dashboard. */
    public function viewQueue(User $user): bool
    {
        return $user->hasPermission('hr.requests.view_any');
    }

    public function view(User $user, HrRequest $request): bool
    {
        if ($this->owns($user, $request)) {
            return true;
        }

        if ($this->isAssignedTo($user, $request)) {
            return true;
        }

        if (! $user->hasPermission('hr.requests.view_any')) {
            return false;
        }

        return ! $request->is_confidential || $user->canViewConfidentialHr();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.requests.create');
    }

    public function comment(User $user, HrRequest $request): bool
    {
        if (! $this->view($user, $request) || ! $user->hasPermission('hr.requests.comment')) {
            return false;
        }

        // Employees cannot reopen a finished conversation; staff can still reply.
        if ($this->owns($user, $request) && ! $user->hasPermission('hr.requests.comment_internal')) {
            $request->loadMissing('status');

            return ! ($request->status?->is_terminal ?? false);
        }

        return true;
    }

    /** Internal notes are never visible to the employee. */
    public function addInternalNote(User $user, HrRequest $request): bool
    {
        return $this->view($user, $request) && $user->hasPermission('hr.requests.comment_internal');
    }

    public function assign(User $user, HrRequest $request, ?User $assignee = null): bool
    {
        if (! $this->view($user, $request)) {
            return false;
        }

        if ($user->hasPermission('hr.requests.assign')) {
            return true;
        }

        $takingOwn = $assignee !== null && (int) $assignee->id === (int) $user->id;

        return $takingOwn && (
            $user->hasPermission('hr.requests.update_status')
            || $user->hasPermission('hr.requests.resolve')
        );
    }

    public function updateStatus(User $user, HrRequest $request): bool
    {
        return $this->view($user, $request)
            && ($user->hasPermission('hr.requests.update_status') || $this->isAssignedTo($user, $request))
            && ! $this->owns($user, $request);
    }

    public function resolve(User $user, HrRequest $request): bool
    {
        return $this->view($user, $request)
            && ($user->hasPermission('hr.requests.resolve') || $this->isAssignedTo($user, $request))
            && ! $this->owns($user, $request);
    }

    /** Only the requester confirms that their own HR request is resolved. */
    public function verify(User $user, HrRequest $request): bool
    {
        return $this->owns($user, $request);
    }

    /** Staff may close as verified when the employee cannot confirm. */
    public function closeAsVerified(User $user, HrRequest $request): bool
    {
        return $this->resolve($user, $request);
    }

    public function refer(User $user, HrRequest $request): bool
    {
        return $this->view($user, $request)
            && $user->hasPermission('hr.requests.refer')
            && ! $this->owns($user, $request);
    }

    /**
     * Answering a referral: the department's own permission, referral managers,
     * or the person the referral was assigned to.
     */
    public function respondToReferral(User $user, HrRequest $request, HrDepartmentReferral $referral): bool
    {
        if ((int) $referral->hr_request_id !== (int) $request->id) {
            return false;
        }

        if ($referral->assigned_to !== null && (int) $referral->assigned_to === (int) $user->id) {
            return true;
        }

        $departmentPermission = match ($referral->department_slug) {
            'finance' => 'hr.referrals.finance',
            'it' => 'hr.referrals.it',
            default => 'hr.referrals.manage',
        };

        return $this->view($user, $request)
            && ($user->hasPermission($departmentPermission) || $user->hasPermission('hr.referrals.manage'));
    }

    private function owns(User $user, HrRequest $request): bool
    {
        return (int) $request->requester_id === (int) $user->id;
    }

    private function isAssignedTo(User $user, HrRequest $request): bool
    {
        return $request->assigned_to !== null
            && (int) $request->assigned_to === (int) $user->id;
    }
}
