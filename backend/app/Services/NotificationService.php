<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdmRequest;
use App\Models\AdmRequestStatus;
use App\Models\HrDepartmentReferral;
use App\Models\HrRequest;
use App\Models\HrRequestStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Administration\AdmRequestService;
use App\Services\Hr\HrRequestService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * In-app notifications. Failures are swallowed so a mail/DB glitch cannot
 * roll back ticket assignment, comments, or verification.
 */
class NotificationService
{
    public const RECENT_LIMIT = 20;

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User $recipient, string $type, string $title, ?string $body = null, array $data = []): ?UserNotification
    {
        try {
            return UserNotification::query()->create([
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => mb_substr($title, 0, 160),
                'body' => $body !== null ? mb_substr($body, 0, 255) : null,
                'data' => $data ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Notification could not be stored.', [
                'type' => $type,
                'user_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  iterable<int, User>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function notifyMany(iterable $recipients, string $type, string $title, ?string $body = null, array $data = []): void
    {
        foreach ($recipients as $recipient) {
            if (! $recipient instanceof User) {
                continue;
            }

            $this->notify($recipient, $type, $title, $body, $data);
        }
    }

    public function ticketCreated(Ticket $ticket, User $actor): void
    {
        $payload = $this->ticketPayload($ticket);

        $this->notify(
            $ticket->requester ?? $actor,
            'ticket.created',
            'Ticket created',
            $ticket->reference.' has been submitted to IT.',
            $payload,
        );

        $this->notifyMany(
            $this->staffExcept($actor->id),
            'ticket.new',
            'New ticket',
            $ticket->reference.': '.$ticket->subject,
            $payload,
        );
    }

    public function ticketAssigned(Ticket $ticket, User $actor, User $assignee): void
    {
        $payload = $this->ticketPayload($ticket);
        $ticket->loadMissing('requester');

        if ($ticket->requester && $ticket->requester->id !== $actor->id) {
            $this->notify(
                $ticket->requester,
                'ticket.assigned',
                'Ticket assigned',
                $ticket->reference.' has been assigned to '.$assignee->full_name.'.',
                $payload,
            );
        }

        if ($assignee->id !== $actor->id) {
            $this->notify(
                $assignee,
                'ticket.assigned',
                'Ticket assigned to you',
                $ticket->reference.' was assigned to you.',
                $payload,
            );
        }
    }

    public function staffCommented(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('requester');

        if (! $ticket->requester || $ticket->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $ticket->requester,
            'ticket.it_commented',
            'IT commented',
            'IT added an update on '.$ticket->reference.'.',
            $this->ticketPayload($ticket),
        );
    }

    public function requesterReplied(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('assignee');
        $payload = $this->ticketPayload($ticket);

        if ($ticket->assignee && $ticket->assignee->id !== $actor->id) {
            $this->notify(
                $ticket->assignee,
                'ticket.user_replied',
                'User replied',
                $actor->full_name.' replied on '.$ticket->reference.'.',
                $payload,
            );

            return;
        }

        $this->notifyMany(
            $this->staffExcept($actor->id),
            'ticket.user_replied',
            'User replied',
            $actor->full_name.' replied on '.$ticket->reference.'.',
            $payload,
        );
    }

    public function informationRequested(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('requester');

        if (! $ticket->requester || $ticket->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $ticket->requester,
            'ticket.info_requested',
            'IT requested information',
            'IT needs more information on '.$ticket->reference.'.',
            $this->ticketPayload($ticket),
        );
    }

    public function ticketResolved(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('requester');

        if (! $ticket->requester || $ticket->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $ticket->requester,
            'ticket.resolved',
            'Ticket resolved',
            $ticket->reference.' has been marked resolved.',
            $this->ticketPayload($ticket),
        );
    }

    public function verificationRequired(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('requester');

        if (! $ticket->requester || $ticket->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $ticket->requester,
            'ticket.verification_required',
            'Please confirm resolution',
            'Confirm whether '.$ticket->reference.' is fixed, or tell IT if you still need help.',
            $this->ticketPayload($ticket),
        );
    }

    public function ticketClosed(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing(['requester', 'assignee']);
        $payload = $this->ticketPayload($ticket);

        if ($ticket->requester && $ticket->requester->id !== $actor->id) {
            $this->notify(
                $ticket->requester,
                'ticket.closed',
                'Ticket closed',
                $ticket->reference.' has been closed.',
                $payload,
            );
        }

        $staff = collect([$ticket->assignee])
            ->filter(fn ($user) => $user instanceof User && $user->id !== $actor->id)
            ->unique('id');

        $this->notifyMany(
            $staff,
            'ticket.verified',
            'Verification completed',
            $actor->full_name.' verified and closed '.$ticket->reference.'.',
            $payload,
        );
    }

    // -----------------------------------------------------------------
    // HR Helpdesk
    // -----------------------------------------------------------------

    public function hrRequestCreated(HrRequest $request, User $actor): void
    {
        $request->loadMissing('requester');
        $payload = $this->hrPayload($request);

        if ($request->requester) {
            $this->notify(
                $request->requester,
                'hr.request.created',
                'HR request submitted',
                $request->reference.' has been submitted to HR.',
                $payload,
            );
        }

        $this->notifyMany(
            $this->hrStaffFor($request, $actor->id),
            'hr.request.new',
            'New HR request',
            $request->is_confidential
                ? $request->reference.': new confidential complaint.'
                : $request->reference.' from '.$request->employee_name.'.',
            $payload,
        );
    }

    public function hrRequestAssigned(HrRequest $request, User $actor, User $assignee): void
    {
        $request->loadMissing('requester');
        $payload = $this->hrPayload($request);

        if ($request->requester && $request->requester->id !== $actor->id) {
            $this->notify(
                $request->requester,
                'hr.request.assigned',
                'HR request assigned',
                $request->reference.' has been assigned to '.$assignee->full_name.'.',
                $payload,
            );
        }

        if ($assignee->id !== $actor->id) {
            $this->notify(
                $assignee,
                'hr.request.assigned',
                'HR request assigned to you',
                $request->reference.' was assigned to you.',
                $payload,
            );
        }
    }

    public function hrRequestStatusChanged(HrRequest $request, User $actor, ?HrRequestStatus $from = null): void
    {
        $request->loadMissing(['requester', 'assignee', 'status']);
        $payload = $this->hrPayload($request);
        $statusName = $request->status?->name ?? 'a new status';

        if ($request->requester && $request->requester->id !== $actor->id) {
            $this->notify(
                $request->requester,
                'hr.request.status_changed',
                'HR request updated',
                $request->reference.' is now '.$statusName.'.',
                $payload + ['from_status' => $from?->slug, 'to_status' => $request->status?->slug],
            );
        }

        if ($request->assignee && $request->assignee->id !== $actor->id) {
            $this->notify(
                $request->assignee,
                'hr.request.status_changed',
                'HR request status changed',
                $actor->full_name.' moved '.$request->reference.' to '.$statusName.'.',
                $payload + ['from_status' => $from?->slug, 'to_status' => $request->status?->slug],
            );
        }
    }

    public function hrRequestCommented(HrRequest $request, User $actor, bool $internal = false): void
    {
        $request->loadMissing(['requester', 'assignee']);
        $payload = $this->hrPayload($request);

        if ($internal) {
            if ($request->assignee && $request->assignee->id !== $actor->id) {
                $this->notify(
                    $request->assignee,
                    'hr.request.commented',
                    'Internal note added',
                    $actor->full_name.' added a note on '.$request->reference.'.',
                    $payload,
                );
            }

            return;
        }

        if ((int) $request->requester_id === (int) $actor->id) {
            if ($request->assignee && $request->assignee->id !== $actor->id) {
                $this->notify(
                    $request->assignee,
                    'hr.request.commented',
                    'Employee replied',
                    $actor->full_name.' replied on '.$request->reference.'.',
                    $payload,
                );

                return;
            }

            $this->notifyMany(
                $this->hrStaffFor($request, $actor->id),
                'hr.request.commented',
                'Employee replied',
                $actor->full_name.' replied on '.$request->reference.'.',
                $payload,
            );

            return;
        }

        if ($request->requester) {
            $this->notify(
                $request->requester,
                'hr.request.commented',
                'HR added an update',
                'HR added an update on '.$request->reference.'.',
                $payload,
            );
        }
    }

    public function hrRequestEscalated(HrRequest $request, User $manager): void
    {
        $request->loadMissing(['assignee', 'requester']);
        $payload = $this->hrPayload($request) + ['escalated_to' => (int) $manager->id];

        $this->notify(
            $manager,
            'hr.request.escalated',
            'HR request escalated',
            $request->reference.' exceeded its SLA and needs your attention.',
            $payload,
        );

        if ($request->assignee && (int) $request->assignee->id !== (int) $manager->id) {
            $this->notify(
                $request->assignee,
                'hr.request.escalated',
                'HR request escalated',
                $request->reference.' was escalated to '.$manager->full_name.' after missing its SLA.',
                $payload,
            );
        }
    }

    public function hrRequestResolved(HrRequest $request, User $actor): void
    {
        $request->loadMissing('requester');

        if (! $request->requester || $request->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $request->requester,
            'hr.request.resolved',
            'HR request resolved',
            $request->reference.' has been marked resolved.',
            $this->hrPayload($request),
        );
    }

    public function hrVerificationRequired(HrRequest $request, User $actor): void
    {
        $request->loadMissing('requester');

        if (! $request->requester || $request->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $request->requester,
            'hr.request.verification_required',
            'Please confirm resolution',
            'Please confirm within '.HrRequestService::VERIFICATION_WINDOW_DAYS.' days whether '.$request->reference.' is resolved, or tell HR if you still need help. It closes automatically if we do not hear back.',
            $this->hrPayload($request),
        );
    }

    public function hrRequestClosed(HrRequest $request, User $actor): void
    {
        $request->loadMissing(['requester', 'assignee', 'status']);
        $payload = $this->hrPayload($request);
        $statusName = strtolower($request->status?->name ?? 'closed');

        if ($request->requester && $request->requester->id !== $actor->id) {
            $this->notify(
                $request->requester,
                'hr.request.closed',
                'HR request '.$statusName,
                $request->reference.' has been '.$statusName.'.',
                $payload,
            );
        }

        // The employee verified the resolution: the whole desk hears about it.
        if ($request->status?->slug === 'closed' && (int) $request->requester_id === (int) $actor->id) {
            $this->notifyMany(
                $this->hrStaffFor($request, $actor->id)
                    ->merge(collect([$request->assignee])->filter(fn ($user) => $user instanceof User))
                    ->reject(fn (User $user) => $user->id === $actor->id)
                    ->unique('id')
                    ->values(),
                'hr.request.verified',
                'Verified by employee',
                $actor->full_name.' verified and closed '.$request->reference.'.',
                $payload,
            );

            return;
        }

        $staff = collect([$request->assignee])
            ->filter(fn ($user) => $user instanceof User && $user->id !== $actor->id)
            ->unique('id');

        $this->notifyMany(
            $staff,
            $request->status?->slug === 'closed' ? 'hr.request.verified' : 'hr.request.closed',
            $request->status?->slug === 'closed' ? 'Verification completed' : 'HR request '.$statusName,
            $request->status?->slug === 'closed'
                ? $actor->full_name.' verified and closed '.$request->reference.'.'
                : $actor->full_name.' marked '.$request->reference.' as '.$statusName.'.',
            $payload,
        );
    }

    public function hrReferralCreated(HrRequest $request, HrDepartmentReferral $referral, User $actor): void
    {
        $request->loadMissing('requester');
        $payload = $this->hrPayload($request) + ['referral_id' => (int) $referral->id];

        if ($referral->assigned_to && (int) $referral->assigned_to !== $actor->id) {
            $recipient = User::query()->active()->find($referral->assigned_to);

            if ($recipient) {
                $this->notify(
                    $recipient,
                    'hr.referral.new',
                    'HR referral for your team',
                    $request->reference.' was referred to '.$referral->department_slug.'.',
                    $payload,
                );
            }
        }

        if ($request->requester && $request->requester->id !== $actor->id && ! $request->is_confidential) {
            $this->notify(
                $request->requester,
                'hr.request.status_changed',
                'HR request updated',
                $request->reference.' is waiting on another department.',
                $payload,
            );
        }
    }

    public function hrReferralResponded(HrRequest $request, HrDepartmentReferral $referral, User $actor): void
    {
        $request->loadMissing('assignee');
        $referral->loadMissing('creator');
        $payload = $this->hrPayload($request) + ['referral_id' => (int) $referral->id];

        $recipients = collect([$request->assignee, $referral->creator])
            ->filter(fn ($user) => $user instanceof User && $user->id !== $actor->id)
            ->unique('id');

        $this->notifyMany(
            $recipients,
            'hr.referral.responded',
            'Referral answered',
            $actor->full_name.' responded to the '.$referral->department_slug.' referral on '.$request->reference.'.',
            $payload,
        );
    }

    // -----------------------------------------------------------------
    // Administration Helpdesk
    // -----------------------------------------------------------------

    public function admRequestCreated(AdmRequest $request, User $actor): void
    {
        $request->loadMissing('requester');
        $payload = $this->admPayload($request);

        if ($request->requester) {
            $this->notify(
                $request->requester,
                'adm.request.created',
                'Administration request submitted',
                $request->reference.' has been submitted to Administration.',
                $payload,
            );
        }

        $this->notifyMany(
            $this->admStaffFor($request, $actor->id),
            'adm.request.new',
            'New Administration request',
            $request->reference.': '.$request->subject,
            $payload,
        );
    }

    public function admRequestAssigned(AdmRequest $request, User $actor, User $assignee): void
    {
        $request->loadMissing('requester');
        $payload = $this->admPayload($request);

        if ($request->requester && $request->requester->id !== $actor->id) {
            $this->notify(
                $request->requester,
                'adm.request.assigned',
                'Administration request assigned',
                $request->reference.' has been assigned to '.$assignee->full_name.'.',
                $payload,
            );
        }

        if ($assignee->id !== $actor->id) {
            $this->notify(
                $assignee,
                'adm.request.assigned',
                'Administration request assigned to you',
                $request->reference.' was assigned to you.',
                $payload,
            );
        }
    }

    public function admRequestStatusChanged(AdmRequest $request, User $actor, ?AdmRequestStatus $from = null): void
    {
        $request->loadMissing(['requester', 'assignee', 'status']);
        $payload = $this->admPayload($request);
        $statusName = $request->status?->name ?? 'a new status';
        $extra = ['from_status' => $from?->slug, 'to_status' => $request->status?->slug];

        if ($request->requester && $request->requester->id !== $actor->id) {
            $this->notify(
                $request->requester,
                'adm.request.status_changed',
                'Administration request updated',
                $request->reference.' is now '.$statusName.'.',
                $payload + $extra,
            );
        }

        if ($request->assignee && $request->assignee->id !== $actor->id) {
            $this->notify(
                $request->assignee,
                'adm.request.status_changed',
                'Administration request status changed',
                $actor->full_name.' moved '.$request->reference.' to '.$statusName.'.',
                $payload + $extra,
            );
        }
    }

    public function admRequestCommented(AdmRequest $request, User $actor, bool $internal = false): void
    {
        $request->loadMissing(['requester', 'assignee']);
        $payload = $this->admPayload($request);

        if ($internal) {
            if ($request->assignee && $request->assignee->id !== $actor->id) {
                $this->notify(
                    $request->assignee,
                    'adm.request.commented',
                    'Internal note added',
                    $actor->full_name.' added a note on '.$request->reference.'.',
                    $payload,
                );
            }

            return;
        }

        if ((int) $request->requester_id === (int) $actor->id) {
            $this->notifyMany(
                $this->admStaffFor($request, $actor->id),
                'adm.request.commented',
                'Employee replied',
                $actor->full_name.' replied on '.$request->reference.'.',
                $payload,
            );

            return;
        }

        if ($request->requester) {
            $this->notify(
                $request->requester,
                'adm.request.commented',
                'Administration added an update',
                'Administration added an update on '.$request->reference.'.',
                $payload,
            );
        }
    }

    public function admRequestResolved(AdmRequest $request, User $actor): void
    {
        $request->loadMissing('requester');

        if (! $request->requester || $request->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $request->requester,
            'adm.request.resolved',
            'Administration request resolved',
            $request->reference.' has been marked resolved.',
            $this->admPayload($request),
        );
    }

    public function admVerificationRequired(AdmRequest $request, User $actor): void
    {
        $request->loadMissing('requester');

        if (! $request->requester || $request->requester->id === $actor->id) {
            return;
        }

        $this->notify(
            $request->requester,
            'adm.request.verification_required',
            'Please confirm resolution',
            'Please confirm within '.AdmRequestService::VERIFICATION_WINDOW_DAYS.' days whether '.$request->reference.' is resolved, or tell Administration if you still need help. It closes automatically if we do not hear back.',
            $this->admPayload($request),
        );
    }

    public function admRequestClosed(AdmRequest $request, User $actor): void
    {
        $request->loadMissing(['requester', 'assignee', 'status']);
        $payload = $this->admPayload($request);
        $statusName = strtolower($request->status?->name ?? 'closed');

        if ($request->requester && $request->requester->id !== $actor->id) {
            $this->notify(
                $request->requester,
                'adm.request.closed',
                'Administration request '.$statusName,
                $request->reference.' has been '.$statusName.'.',
                $payload,
            );
        }

        // The employee verified the resolution: the whole desk hears about it.
        if ($request->status?->slug === 'closed' && (int) $request->requester_id === (int) $actor->id) {
            $this->notifyMany(
                $this->admStaffFor($request, $actor->id)
                    ->merge(collect([$request->assignee])->filter(fn ($user) => $user instanceof User))
                    ->reject(fn (User $user) => $user->id === $actor->id)
                    ->unique('id')
                    ->values(),
                'adm.request.verified',
                'Verified by employee',
                $actor->full_name.' verified and closed '.$request->reference.'.',
                $payload,
            );

            return;
        }

        $staff = collect([$request->assignee])
            ->filter(fn ($user) => $user instanceof User && $user->id !== $actor->id)
            ->unique('id');

        $this->notifyMany(
            $staff,
            $request->status?->slug === 'closed' ? 'adm.request.verified' : 'adm.request.closed',
            $request->status?->slug === 'closed' ? 'Verification completed' : 'Administration request '.$statusName,
            $request->status?->slug === 'closed'
                ? $actor->full_name.' verified and closed '.$request->reference.'.'
                : $actor->full_name.' marked '.$request->reference.' as '.$statusName.'.',
            $payload,
        );
    }

    /**
     * @param  'it'|'hr'|'administration'|null  $module
     * @return Collection<int, UserNotification>
     */
    public function recent(User $user, int $limit = self::RECENT_LIMIT, ?string $module = null): Collection
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->tap(fn ($query) => $this->applyModuleFilter($query, $module))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  'it'|'hr'|'administration'|null  $module
     */
    public function unreadCount(User $user, ?string $module = null): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->tap(fn ($query) => $this->applyModuleFilter($query, $module))
            ->count();
    }

    public function markRead(User $user, UserNotification $notification): UserNotification
    {
        if ((int) $notification->user_id !== (int) $user->id) {
            abort(404);
        }

        $notification->markRead();

        return $notification->fresh() ?? $notification;
    }

    /**
     * @param  'it'|'hr'|'administration'|null  $module
     */
    public function markAllRead(User $user, ?string $module = null): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->tap(fn ($query) => $this->applyModuleFilter($query, $module))
            ->update(['read_at' => now()]);
    }

    /**
     * Keep IT and HR notification streams separate in each desk shell.
     * Filter by type prefix only — SQL Server does not support Laravel's
     * data->json path helpers used elsewhere on MySQL.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\UserNotification>|\Illuminate\Database\Query\Builder  $query
     * @param  'it'|'hr'|'administration'|null  $module
     */
    private function applyModuleFilter($query, ?string $module): void
    {
        if ($module === 'hr') {
            $query->where('type', 'like', 'hr.%');

            return;
        }

        if ($module === 'administration') {
            $query->where('type', 'like', 'adm.%');

            return;
        }

        if ($module === 'it') {
            $query->where('type', 'not like', 'hr.%')->where('type', 'not like', 'adm.%');
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function staffExcept(int $actorId): Collection
    {
        return User::query()
            ->staff()
            ->active()
            ->where('id', '<>', $actorId)
            ->get();
    }

    /**
     * Active HR staff who are allowed to see the request (confidential
     * complaints are limited to cleared handlers and the assignee).
     *
     * @return Collection<int, User>
     */
    private function hrStaffFor(HrRequest $request, int $actorId): Collection
    {
        return User::query()
            ->hrStaff()
            ->active()
            ->where('id', '<>', $actorId)
            ->with('role.permissions')
            ->get()
            ->filter(fn (User $user) => $user->can('view', $request))
            ->values();
    }

    /**
     * @return array{module: string, hr_request_id: int, reference: string}
     */
    private function hrPayload(HrRequest $request): array
    {
        return [
            'module' => 'hr',
            'hr_request_id' => (int) $request->id,
            'reference' => (string) $request->reference,
        ];
    }

    /**
     * Active Administration desk staff allowed to see the request.
     *
     * @return Collection<int, User>
     */
    private function admStaffFor(AdmRequest $request, int $actorId): Collection
    {
        return User::query()
            ->administrationStaff()
            ->active()
            ->where('id', '<>', $actorId)
            ->with('role.permissions')
            ->get()
            ->filter(fn (User $user) => $user->can('view', $request))
            ->values();
    }

    /**
     * @return array{module: string, adm_request_id: int, reference: string}
     */
    private function admPayload(AdmRequest $request): array
    {
        return [
            'module' => 'administration',
            'adm_request_id' => (int) $request->id,
            'reference' => (string) $request->reference,
        ];
    }
    /**
     * @return array{module: string, ticket_id: int, reference: string}
     */
    private function ticketPayload(Ticket $ticket): array
    {
        return [
            'module' => 'it',
            'ticket_id' => (int) $ticket->id,
            'reference' => (string) $ticket->reference,
        ];
    }
}
