<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\RoleSlug;
use App\Models\HrCategory;
use App\Models\HrDepartmentReferral;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\HrRequestComment;
use App\Models\HrRequestStatus;
use App\Models\HrRequestStatusHistory;
use App\Models\HrRequestType;
use App\Models\HrSlaSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writes HR requests and their workflow (assignment, status, referrals).
 *
 * Ownership, status, confidentiality, due date and the public reference are
 * decided here so the request body cannot choose them. Authorization is the
 * caller's job (policy); this class only enforces workflow invariants.
 */
class HrRequestService
{
    /** Statuses a freshly assigned request moves out of. */
    private const PRE_ASSIGNMENT_STATUSES = ['submitted', 'under-review'];

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'cpl', 'dll', 'js', 'jse', 'msi', 'msp',
        'pif', 'ps1', 'scr', 'vbe', 'vbs', 'wsf', 'wsh', 'sh', 'php', 'phar',
        'jar', 'hta', 'lnk',
    ];

    public function __construct(
        private readonly HrReferenceGenerator $references,
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{category_id: int, request_type_id: int, description: string}  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(User $user, array $data, array $files = []): HrRequest
    {
        $category = HrCategory::query()->active()->whereKey($data['category_id'])->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category is not available.'],
            ]);
        }

        $type = HrRequestType::query()
            ->active()
            ->where('hr_category_id', $category->id)
            ->whereKey($data['request_type_id'])
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'request_type_id' => ['The selected request type is not available for this category.'],
            ]);
        }

        $status = $this->defaultStatus();
        $user->loadMissing(['department', 'branch']);

        $request = DB::transaction(function () use ($user, $data, $files, $category, $type, $status) {
            $request = $this->insertWithUniqueReference($user, $data, $category, $type, $status);

            $this->recordHistory($request, null, $status, $user, 'Request submitted.');

            if ($files !== []) {
                $this->storeAttachments($request, $user, $files);
            }

            return $this->assignToAllHrStaff($request, $user);
        });

        $this->audit->log(
            event: 'hr_request.created',
            subject: $request,
            newValues: [
                'reference' => $request->reference,
                'hr_category_id' => $request->hr_category_id,
                'hr_request_type_id' => $request->hr_request_type_id,
                'status_id' => $request->status_id,
                'is_confidential' => $request->is_confidential,
                'assigned_to' => $request->assigned_to,
                'assigned_to_team' => true,
            ],
            description: $user->username.' submitted HR request '.$request->reference,
        );

        $request->load(['requester', 'category', 'requestType', 'status', 'assignee', 'assignments']);
        $this->notifications->hrRequestCreated($request, $user);

        return $request->load(['branch', 'assignee', 'attachments', 'assignments']);
    }

    /**
     * Put every eligible HR staff member on the request so the desk can work it
     * without a separate take/assign step — same pattern as IT tickets.
     */
    public function assignToAllHrStaff(HrRequest $request, User $actor): HrRequest
    {
        $staff = User::query()
            ->hrStaff()
            ->active()
            ->with('role.permissions')
            ->orderBy('id')
            ->get();

        if ($request->is_confidential) {
            $staff = $staff
                ->filter(fn (User $member): bool => $member->canViewConfidentialHr())
                ->values();
        }

        if ($staff->isEmpty()) {
            return $request;
        }

        $primary = $staff->first(
            fn (User $member): bool => $member->hasRole(
                RoleSlug::HrManager,
                RoleSlug::Administrator,
                RoleSlug::SuperAdmin,
            ),
        ) ?? $staff->first();

        return DB::transaction(function () use ($request, $actor, $staff, $primary) {
            $request = HrRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing('status');

            $request->assignments()->current()->update(['released_at' => now()]);

            foreach ($staff as $member) {
                $request->assignments()->create([
                    'assigned_to' => $member->id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'note' => 'Automatically assigned to the HR desk.',
                ]);
            }

            $request->forceFill([
                'assigned_to' => $primary->id,
                'assigned_at' => now(),
            ]);

            if (in_array($request->status?->slug, self::PRE_ASSIGNMENT_STATUSES, true)) {
                $assigned = HrRequestStatus::query()->where('slug', 'assigned')->first();

                if ($assigned) {
                    $from = $request->status;
                    $request->status_id = $assigned->id;
                    $request->setRelation('status', $assigned);
                    $request->save();
                    $this->recordHistory(
                        $request,
                        $from,
                        $assigned,
                        $actor,
                        'Assigned to all HR staff.',
                    );

                    return $request->fresh() ?? $request;
                }
            }

            $request->save();

            return $request->fresh() ?? $request;
        });
    }

    public function addComment(HrRequest $request, User $actor, string $body, bool $internal = false): HrRequestComment
    {
        $request->loadMissing('status');

        $comment = DB::transaction(function () use ($request, $actor, $body, $internal) {
            $comment = $request->comments()->create([
                'author_id' => $actor->id,
                'body' => $body,
                'is_internal' => $internal,
                'is_system' => false,
            ]);

            // The employee answering an information request puts the case back to work.
            if (
                ! $internal
                && (int) $request->requester_id === (int) $actor->id
                && $request->status?->slug === 'awaiting-employee-information'
            ) {
                $inProgress = HrRequestStatus::query()->where('slug', 'in-progress')->first();

                if ($inProgress) {
                    $this->moveToStatus($request, $inProgress, $actor, 'Employee replied.');
                }
            } else {
                $request->touch();
            }

            return $comment;
        });

        $this->audit->log(
            event: $internal ? 'hr_request.internal_note_added' : 'hr_request.comment_added',
            subject: $request,
            newValues: ['comment_id' => $comment->id],
            description: $actor->username.' commented on '.$request->reference,
        );

        $request->loadMissing(['requester', 'assignee']);
        $this->notifications->hrRequestCommented($request, $actor, $internal);

        return $comment->load(['author.role', 'attachments']);
    }

    public function assign(HrRequest $request, User $actor, User $assignee, ?string $note = null): HrRequest
    {
        $request->loadMissing('status');
        $this->assertOpen($request, 'assigned_to');

        if (! $assignee->isHrStaff() || ! $assignee->canAccessSystem()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['The selected user cannot be assigned HR requests.'],
            ]);
        }

        if ($request->is_confidential && ! $assignee->canViewConfidentialHr() && ! $this->isCurrentAssignee($request, $assignee)) {
            throw ValidationException::withMessages([
                'assigned_to' => ['The selected user is not cleared to handle confidential complaints.'],
            ]);
        }

        $previousAssignee = $request->assigned_to;

        DB::transaction(function () use ($request, $actor, $assignee, $note): void {
            $request->assignments()->current()->update(['released_at' => now()]);

            $request->assignments()->create([
                'assigned_to' => $assignee->id,
                'assigned_by' => $actor->id,
                'note' => $note,
                'assigned_at' => now(),
            ]);

            $request->forceFill([
                'assigned_to' => $assignee->id,
                'assigned_at' => now(),
            ])->save();

            if (in_array($request->status?->slug, self::PRE_ASSIGNMENT_STATUSES, true)) {
                $assigned = HrRequestStatus::query()->where('slug', 'assigned')->first();

                if ($assigned) {
                    $this->moveToStatus($request, $assigned, $actor, $note ?: 'Request assigned to '.$assignee->full_name.'.');
                }
            }
        });

        $this->audit->log(
            event: 'hr_request.assigned',
            subject: $request,
            oldValues: ['assigned_to' => $previousAssignee],
            newValues: ['assigned_to' => $assignee->id],
            description: $actor->username.' assigned '.$request->reference.' to '.$assignee->username,
        );

        $request->loadMissing(['requester']);
        $this->notifications->hrRequestAssigned($request, $actor, $assignee);

        return $request;
    }

    /**
     * Moves a request to another status. Resolved, closed and rejected need a
     * note, which becomes the resolution summary shown to the employee.
     */
    public function updateStatus(HrRequest $request, User $actor, HrRequestStatus $status, ?string $remarks = null): HrRequest
    {
        $request->loadMissing('status');
        $from = $request->status;
        $this->assertOpen($request, 'status_id');

        if (! $status->is_active || $status->slug === 'submitted') {
            throw ValidationException::withMessages([
                'status_id' => ['The selected status cannot be applied.'],
            ]);
        }

        if ($from && (int) $from->id === (int) $status->id) {
            throw ValidationException::withMessages([
                'status_id' => ['The request is already in that status.'],
            ]);
        }

        if ($status->slug === 'closed') {
            throw ValidationException::withMessages([
                'status_id' => ['Requests can only be closed after the employee confirms the resolution.'],
            ]);
        }

        $note = $this->cleanNote($remarks);

        if (in_array($status->slug, ['resolved', 'rejected'], true) && $note === null) {
            throw ValidationException::withMessages([
                'remarks' => ['Add a short note explaining the outcome.'],
            ]);
        }

        if ($status->slug === 'resolved') {
            return $this->resolve($request, $actor, (string) $note);
        }

        if (in_array($status->slug, ['assigned', 'in-progress', 'under-review'], true)) {
            $request = $this->claimIfUnassigned($request, $actor);
        }

        DB::transaction(function () use ($request, $actor, $status, $note): void {
            $this->applyOutcomeFields($request, $actor, $status, $note);
            $this->moveToStatus($request, $status, $actor, $note);
        });

        $this->audit->log(
            event: 'hr_request.status_changed',
            subject: $request,
            oldValues: ['status_id' => $from?->id],
            newValues: ['status_id' => $status->id],
            description: $actor->username.' changed '.$request->reference.' to '.$status->name,
        );

        $request->load(['requester', 'assignee', 'status', 'assignments']);

        if ($status->is_terminal) {
            $this->notifications->hrRequestClosed($request, $actor);
        } else {
            $this->notifications->hrRequestStatusChanged($request, $actor, $from);
        }

        return $request;
    }

    /**
     * Marks the request resolved and asks the employee to confirm — same as IT.
     */
    public function resolve(HrRequest $request, User $actor, string $summary, bool $close = false): HrRequest
    {
        // Closing without employee confirmation is a separate staff action.
        if ($close) {
            return $this->closeAsVerified(
                $this->resolve($request, $actor, $summary, false),
                $actor,
            );
        }

        $summary = trim($summary);

        if ($summary === '') {
            throw ValidationException::withMessages([
                'resolution_summary' => ['Describe how the request was resolved.'],
            ]);
        }

        $resolved = HrRequestStatus::query()->where('slug', 'resolved')->first();

        if (! $resolved) {
            throw new RuntimeException('The HR request statuses have not been seeded.');
        }

        $request->loadMissing('status');
        $this->assertOpen($request, 'resolution_summary');
        $request = $this->claimIfUnassigned($request, $actor);

        $fromId = $request->status_id;

        $request = DB::transaction(function () use ($request, $actor, $resolved, $summary) {
            $request = HrRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing('status');

            $this->applyOutcomeFields($request, $actor, $resolved, $summary);
            $this->moveToStatus($request, $resolved, $actor, $summary);

            return $request->fresh() ?? $request;
        });

        $this->audit->log(
            event: 'hr_request.resolved',
            subject: $request,
            oldValues: ['status_id' => $fromId],
            newValues: ['status' => 'resolved'],
            description: $actor->username.' resolved '.$request->reference,
        );

        $request->load(['requester', 'assignee', 'status', 'assignments', 'resolver']);
        $this->notifications->hrRequestResolved($request, $actor);
        $this->notifications->hrVerificationRequired($request, $actor);

        return $request;
    }

    /**
     * Employee confirms the fix and closes the request.
     */
    public function confirmResolution(HrRequest $request, User $actor): HrRequest
    {
        $closed = HrRequestStatus::query()->where('slug', 'closed')->first();

        if (! $closed) {
            throw new RuntimeException('The HR request statuses have not been seeded.');
        }

        $updated = DB::transaction(function () use ($request, $actor, $closed) {
            $request = HrRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing(['status', 'assignments']);

            if ($request->status?->is_terminal || $request->status?->slug === 'closed') {
                return $request;
            }

            if (! $request->isAwaitingVerification()) {
                throw ValidationException::withMessages([
                    'decision' => ['This resolution can no longer be confirmed.'],
                ]);
            }

            $request->assertIndividualAssigneeForClose();

            $request->forceFill([
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);
            $this->moveToStatus($request, $closed, $actor, 'Employee confirmed the resolution.');

            $this->audit->log(
                event: 'hr_request.verified',
                subject: $request,
                newValues: ['status' => 'closed', 'verified_by' => $actor->id],
                description: $actor->username.' confirmed and closed '.$request->reference,
            );

            $this->audit->log(
                event: 'hr_request.closed',
                subject: $request,
                newValues: ['status' => 'closed', 'closed_by' => $actor->id],
                description: $actor->username.' closed '.$request->reference,
            );

            return $request->fresh() ?? $request;
        });

        $updated->load(['requester', 'assignee', 'status', 'assignments']);
        $this->notifications->hrRequestClosed($updated, $actor);

        return $updated;
    }

    /**
     * Employee rejects the fix so HR can keep working.
     */
    public function rejectResolution(HrRequest $request, User $actor): HrRequest
    {
        $inProgress = HrRequestStatus::query()->where('slug', 'in-progress')->first();

        if (! $inProgress) {
            throw new RuntimeException('The HR request statuses have not been seeded.');
        }

        $updated = DB::transaction(function () use ($request, $actor, $inProgress) {
            $request = HrRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing('status');

            if (! $request->isAwaitingVerification()) {
                throw ValidationException::withMessages([
                    'decision' => ['This resolution can no longer be confirmed.'],
                ]);
            }

            $summary = $request->resolution_summary;

            $request->forceFill([
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution_summary' => $summary,
            ]);
            $this->moveToStatus(
                $request,
                $inProgress,
                $actor,
                'Employee reported the issue is not resolved.',
            );

            $this->audit->log(
                event: 'hr_request.verification_rejected',
                subject: $request,
                newValues: ['status' => 'in-progress', 'rejected_by' => $actor->id],
                description: $actor->username.' rejected the resolution for '.$request->reference,
            );

            return $request->fresh() ?? $request;
        });

        $updated->load(['requester', 'assignee', 'status', 'assignments']);
        $this->notifications->hrRequestStatusChanged($updated, $actor);

        return $updated;
    }

    /**
     * Staff close as if the employee confirmed — used when confirmation is unavailable.
     */
    public function closeAsVerified(HrRequest $request, User $actor): HrRequest
    {
        $closed = HrRequestStatus::query()->where('slug', 'closed')->first();

        if (! $closed) {
            throw new RuntimeException('The HR request statuses have not been seeded.');
        }

        $updated = DB::transaction(function () use ($request, $actor, $closed) {
            $request = HrRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing(['status', 'requester', 'assignments']);

            if ($request->status?->is_terminal || $request->status?->slug === 'closed') {
                throw ValidationException::withMessages([
                    'status_id' => ['This request is already closed.'],
                ]);
            }

            $request->assertIndividualAssigneeForClose();

            if (! $request->isAwaitingVerification() && blank($request->resolution_summary)) {
                throw ValidationException::withMessages([
                    'status_id' => ['Resolve the request first, or add a resolution before closing as verified.'],
                ]);
            }

            $requesterId = (int) $request->requester_id;
            $now = now();

            $request->forceFill([
                'resolved_at' => $request->resolved_at ?? $now,
                'resolved_by' => $request->resolved_by ?? $actor->id,
                'resolution_summary' => $request->resolution_summary ?: 'Resolved by HR.',
                'closed_by' => $requesterId,
                'closed_at' => $now,
            ]);
            $this->moveToStatus($request, $closed, $actor, 'Closed as verified by HR on behalf of the employee.');

            $this->audit->log(
                event: 'hr_request.verified',
                subject: $request,
                newValues: [
                    'status' => 'closed',
                    'verified_by' => $requesterId,
                    'closed_on_behalf_by' => $actor->id,
                ],
                description: $actor->username.' closed '.$request->reference.' as verified by the employee',
            );

            return $request->fresh() ?? $request;
        });

        $updated->load(['requester', 'assignee', 'status', 'assignments']);
        $this->notifications->hrRequestClosed($updated, $actor);

        return $updated;
    }

    private function claimIfUnassigned(HrRequest $request, User $actor): HrRequest
    {
        $request->loadMissing('assignments');

        if ($request->hasIndividualAssignee()) {
            return $request;
        }

        if (! $actor->isHrStaff()) {
            throw ValidationException::withMessages([
                'status_id' => ['Assign the request before changing its status.'],
            ]);
        }

        return $this->assign($request, $actor, $actor);
    }

    public function createReferral(
        HrRequest $request,
        User $actor,
        string $departmentSlug,
        ?string $brief = null,
        ?int $assignedTo = null,
    ): HrDepartmentReferral {
        $request->loadMissing('status');
        $this->assertOpen($request, 'department');

        $departments = collect(config('hrhelpdesk.referral_departments', []))->pluck('slug')->all();

        if (! in_array($departmentSlug, $departments, true)) {
            throw ValidationException::withMessages([
                'department' => ['The selected department is not available for referrals.'],
            ]);
        }

        $referral = DB::transaction(function () use ($request, $actor, $departmentSlug, $brief, $assignedTo) {
            $referral = $request->referrals()->create([
                'department_slug' => $departmentSlug,
                'status' => 'pending',
                'created_by' => $actor->id,
                'assigned_to' => $assignedTo,
                'brief' => $brief,
            ]);

            $waiting = HrRequestStatus::query()->where('slug', 'awaiting-other-department')->first();

            if ($waiting && (int) $request->status_id !== (int) $waiting->id) {
                $this->moveToStatus($request, $waiting, $actor, 'Referred to '.$departmentSlug.'.');
            } else {
                $request->touch();
            }

            return $referral;
        });

        $this->audit->log(
            event: 'hr_request.referred',
            subject: $request,
            newValues: [
                'referral_id' => $referral->id,
                'department_slug' => $departmentSlug,
                'assigned_to' => $assignedTo,
            ],
            description: $actor->username.' referred '.$request->reference.' to '.$departmentSlug,
        );

        $request->load(['requester', 'assignee', 'status']);
        $this->notifications->hrReferralCreated($request, $referral, $actor);

        return $referral->load(['creator', 'assignee']);
    }

    public function respondToReferral(HrDepartmentReferral $referral, User $actor, string $response): HrDepartmentReferral
    {
        if ($referral->status !== 'pending') {
            throw ValidationException::withMessages([
                'response' => ['This referral has already been answered.'],
            ]);
        }

        $referral->loadMissing('request.status');
        $request = $referral->request;

        DB::transaction(function () use ($referral, $request, $actor, $response): void {
            $referral->forceFill([
                'status' => 'responded',
                'response' => $response,
                'responded_at' => now(),
                'responded_by' => $actor->id,
            ])->save();

            $stillPending = $request->referrals()->where('status', 'pending')->exists();

            if (! $stillPending && $request->status?->slug === 'awaiting-other-department') {
                $inProgress = HrRequestStatus::query()->where('slug', 'in-progress')->first();

                if ($inProgress) {
                    $this->moveToStatus($request, $inProgress, $actor, 'Referral answered by '.$referral->department_slug.'.');

                    return;
                }
            }

            $request->touch();
        });

        $this->audit->log(
            event: 'hr_request.referral_responded',
            subject: $request,
            newValues: ['referral_id' => $referral->id, 'department_slug' => $referral->department_slug],
            description: $actor->username.' responded to the '.$referral->department_slug.' referral on '.$request->reference,
        );

        $request->load(['requester', 'assignee', 'status']);
        $this->notifications->hrReferralResponded($request, $referral, $actor);

        return $referral->load(['creator', 'assignee', 'responder']);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private function defaultStatus(): HrRequestStatus
    {
        $status = HrRequestStatus::query()->active()->where('is_default', true)->first()
            ?? HrRequestStatus::query()->active()->where('slug', 'submitted')->first();

        if (! $status) {
            throw new RuntimeException('The default HR request status has not been seeded.');
        }

        return $status;
    }

    /**
     * @param  array{category_id: int, request_type_id: int, description: string}  $data
     */
    private function insertWithUniqueReference(
        User $user,
        array $data,
        HrCategory $category,
        HrRequestType $type,
        HrRequestStatus $status,
    ): HrRequest {
        $attempts = 0;
        $attributes = $user->getAttributes();

        while (true) {
            $attempts++;

            try {
                return HrRequest::query()->create([
                    'reference' => $this->references->next(),
                    'requester_id' => $user->id,
                    'employee_id' => (string) (($attributes['employee_id'] ?? null) ?: $user->username),
                    'employee_name' => (string) $user->full_name,
                    'department_name' => $user->department?->name,
                    'designation' => $attributes['job_title'] ?? null,
                    'branch_id' => $attributes['branch_id'] ?? null,
                    'department_id' => $attributes['department_id'] ?? null,
                    'hr_category_id' => $category->id,
                    'hr_request_type_id' => $type->id,
                    'status_id' => $status->id,
                    'description' => $data['description'],
                    'is_confidential' => (bool) $category->is_confidential,
                    'due_at' => $this->resolveDueAt($category, $type),
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempts >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Type-level SLA wins over category-level; configured SLA rows win over
     * the defaults stored on the type/category themselves.
     */
    private function resolveDueAt(HrCategory $category, HrRequestType $type): ?Carbon
    {
        $hours = HrSlaSetting::query()
            ->where('is_active', true)
            ->where('hr_request_type_id', $type->id)
            ->value('target_hours');

        $hours ??= HrSlaSetting::query()
            ->where('is_active', true)
            ->where('hr_category_id', $category->id)
            ->whereNull('hr_request_type_id')
            ->value('target_hours');

        $hours ??= $type->sla_hours ?? $category->default_sla_hours;

        return $hours !== null && (int) $hours > 0 ? now()->addHours((int) $hours) : null;
    }

    private function assertOpen(HrRequest $request, string $field): void
    {
        if ($request->status?->is_terminal) {
            throw ValidationException::withMessages([
                $field => ['This request is already closed and can no longer be changed.'],
            ]);
        }
    }

    private function isCurrentAssignee(HrRequest $request, User $user): bool
    {
        return $request->assigned_to !== null && (int) $request->assigned_to === (int) $user->id;
    }

    private function cleanNote(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        return $note === '' ? null : $note;
    }

    /**
     * Stamps resolved/closed bookkeeping before the status itself is moved.
     */
    private function applyOutcomeFields(HrRequest $request, User $actor, HrRequestStatus $status, ?string $note): void
    {
        $fields = [];

        switch ($status->slug) {
            case 'resolved':
                $fields = [
                    'resolution_summary' => $note,
                    'resolved_by' => $actor->id,
                    'resolved_at' => now(),
                ];
                break;

            case 'closed':
                $fields = [
                    'closed_by' => $actor->id,
                    'closed_at' => now(),
                ];

                if ($request->resolved_at === null) {
                    $fields += [
                        'resolution_summary' => $note,
                        'resolved_by' => $actor->id,
                        'resolved_at' => now(),
                    ];
                }
                break;

            case 'rejected':
                $fields = [
                    'resolution_summary' => $note,
                    'closed_by' => $actor->id,
                    'closed_at' => now(),
                ];
                break;

            default:
                // Reopening or continuing work clears any previous outcome.
                if ($request->resolved_at !== null) {
                    $fields = [
                        'resolution_summary' => null,
                        'resolved_by' => null,
                        'resolved_at' => null,
                    ];
                }
        }

        if ($fields !== []) {
            $request->forceFill($fields);
        }
    }

    /**
     * Persists the status change together with any pending attribute changes
     * and writes the matching history row.
     */
    private function moveToStatus(HrRequest $request, HrRequestStatus $status, User $actor, ?string $remarks): void
    {
        $from = $request->status;

        $request->forceFill(['status_id' => $status->id])->save();
        $request->setRelation('status', $status);

        $this->recordHistory($request, $from, $status, $actor, $remarks);
    }

    private function recordHistory(HrRequest $request, ?HrRequestStatus $from, HrRequestStatus $to, ?User $actor, ?string $remarks): HrRequestStatusHistory
    {
        return $request->statusHistories()->create([
            'from_status_id' => $from?->id,
            'to_status_id' => $to->id,
            'changed_by' => $actor?->id,
            'remarks' => $remarks !== null ? mb_substr($remarks, 0, 2000) : null,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, HrRequestAttachment>
     */
    private function storeAttachments(HrRequest $request, User $uploader, array $files): array
    {
        $max = (int) config('hrhelpdesk.attachments.max_per_request');

        if ($request->attachments()->count() + count($files) > $max) {
            throw ValidationException::withMessages([
                'attachments' => ['A request may have at most '.$max.' attachments.'],
            ]);
        }

        $stored = [];

        foreach ($files as $file) {
            $stored[] = $this->storeAttachment($request, $uploader, $file);
        }

        return $stored;
    }

    private function storeAttachment(HrRequest $request, User $uploader, UploadedFile $file): HrRequestAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $allowed = (array) config('hrhelpdesk.attachments.allowed_extensions');
        $maxKilobytes = (int) config('hrhelpdesk.attachments.max_kilobytes');

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => ['Executable and script files are not allowed.'],
            ]);
        }

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'attachments' => ['This file type is not allowed.'],
            ]);
        }

        if ((int) ceil(($file->getSize() ?: 0) / 1024) > $maxKilobytes) {
            throw ValidationException::withMessages([
                'attachments' => ['Each file must be '.$maxKilobytes.' KB or smaller.'],
            ]);
        }

        $disk = (string) config('hrhelpdesk.attachments.disk');
        $directory = trim((string) config('hrhelpdesk.attachments.directory'), '/').'/'.$request->id;
        $path = $file->storeAs($directory, Str::uuid()->toString().'.'.$extension, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'attachments' => ['The attachment could not be stored. Please try again.'],
            ]);
        }

        return $request->attachments()->create([
            'uploaded_by' => $uploader->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }

    public function downloadAttachment(HrRequestAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404, 'The attachment is no longer available.');
        }

        $original = pathinfo($attachment->original_name, PATHINFO_FILENAME);
        $extension = pathinfo($attachment->path, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original) ?: 'attachment';

        return $disk->download(
            $attachment->path,
            $safe.($extension !== '' ? '.'.$extension : ''),
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
