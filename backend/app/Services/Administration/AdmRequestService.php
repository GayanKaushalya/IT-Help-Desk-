<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\RoleSlug;
use App\Models\AdmCategory;
use App\Models\AdmRequest;
use App\Models\AdmRequestAttachment;
use App\Models\AdmRequestComment;
use App\Models\AdmRequestStatus;
use App\Models\AdmRequestStatusHistory;
use App\Models\AdmRequestType;
use App\Models\AdmSlaSetting;
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
 * Writes Administration requests and their workflow (assignment, status, resolution).
 *
 * Ownership, status, due date and the public reference are
 * decided here so the request body cannot choose them. Authorization is the
 * caller's job (policy); this class only enforces workflow invariants.
 */
class AdmRequestService
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
        private readonly AdmReferenceGenerator $references,
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly CctvApprovalService $cctv,
    ) {}

    /**
     * @param  array{
     *     category_id: int,
     *     request_type_id: int,
     *     subject: string,
     *     description: string,
     *     required_date?: ?string,
     *     company_name?: ?string,
     *     employee_id?: ?string,
     *     employee_name?: ?string,
     *     department_name?: ?string,
     *     designation?: ?string,
     *     contact_phone?: ?string
     * }  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(User $user, array $data, array $files = []): AdmRequest
    {
        $category = AdmCategory::query()->active()->whereKey($data['category_id'])->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category is not available.'],
            ]);
        }

        $type = AdmRequestType::query()
            ->active()
            ->where('adm_category_id', $category->id)
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
            $this->cctv->ensureRecord($request->setRelation('category', $category), $user);

            if ($files !== []) {
                $this->storeAttachments($request, $user, $files);
            }

            return $this->assignToAllAdmStaff($request, $user);
        });

        $this->audit->log(
            event: 'adm_request.created',
            subject: $request,
            newValues: [
                'reference' => $request->reference,
                'adm_category_id' => $request->adm_category_id,
                'adm_request_type_id' => $request->adm_request_type_id,
                'status_id' => $request->status_id,
                'assigned_to' => $request->assigned_to,
                'assigned_to_team' => true,
            ],
            description: $user->username.' submitted Administration request '.$request->reference,
        );

        $request->load(['requester', 'category', 'requestType', 'status', 'assignee', 'assignments']);
        $this->notifications->admRequestCreated($request, $user);

        return $request->load(['branch', 'assignee', 'attachments', 'assignments']);
    }

    /**
     * Put every eligible Administration staff member on the request so the desk can work it
     * without a separate take/assign step — same pattern as IT tickets.
     */
    public function assignToAllAdmStaff(AdmRequest $request, User $actor): AdmRequest
    {
        $request->loadMissing('category');

        $agents = User::query()
            ->assignableAdmAgents()
            ->with('role.permissions')
            ->orderBy('id')
            ->get();

        // The general desk always sees new work; specialists join when the
        // request falls in a category they own.
        $routedRoles = [
            RoleSlug::AdministrationOfficer->value,
            RoleSlug::AdministrationManager->value,
            ...(array) config('administration.category_roles.'.$request->category?->slug, []),
        ];

        $staff = $agents
            ->filter(fn (User $member): bool => in_array($member->role?->slug, $routedRoles, true))
            ->values();

        if ($staff->isEmpty()) {
            $staff = $agents;
        }

        if ($staff->isEmpty()) {
            return $request;
        }

        $primary = $staff->first(
            fn (User $member): bool => $member->hasRole(RoleSlug::AdministrationManager),
        ) ?? $staff->first();

        return DB::transaction(function () use ($request, $actor, $staff, $primary) {
            $request = AdmRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing('status');

            $request->assignments()->current()->update(['released_at' => now()]);

            foreach ($staff as $member) {
                $request->assignments()->create([
                    'assigned_to' => $member->id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'note' => 'Automatically assigned to the Administration desk.',
                ]);
            }

            $request->forceFill([
                'assigned_to' => $primary->id,
                'assigned_at' => now(),
            ]);

            if (in_array($request->status?->slug, self::PRE_ASSIGNMENT_STATUSES, true)) {
                $assigned = AdmRequestStatus::query()->where('slug', 'assigned')->first();

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
                        'Assigned to all Administration staff.',
                    );

                    return $request->fresh() ?? $request;
                }
            }

            $request->save();

            return $request->fresh() ?? $request;
        });
    }

    public function addComment(AdmRequest $request, User $actor, string $body, bool $internal = false): AdmRequestComment
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
                $inProgress = AdmRequestStatus::query()->where('slug', 'in-progress')->first();

                if ($inProgress) {
                    $this->moveToStatus($request, $inProgress, $actor, 'Employee replied.');
                }
            } else {
                $request->touch();
            }

            return $comment;
        });

        $this->audit->log(
            event: $internal ? 'adm_request.internal_note_added' : 'adm_request.comment_added',
            subject: $request,
            newValues: ['comment_id' => $comment->id],
            description: $actor->username.' commented on '.$request->reference,
        );

        $request->loadMissing(['requester', 'assignee']);
        $this->notifications->admRequestCommented($request, $actor, $internal);

        return $comment->load(['author.role', 'attachments']);
    }

    public function assign(AdmRequest $request, User $actor, User $assignee, ?string $note = null): AdmRequest
    {
        $request->loadMissing('status');
        $this->assertOpen($request, 'assigned_to');
        $this->cctv->ensureRecord($request, $actor);

        if (! $assignee->isAssignableAdmAgent()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['The selected user cannot be assigned Administration requests.'],
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
                $assigned = AdmRequestStatus::query()->where('slug', 'assigned')->first();

                if ($assigned) {
                    $this->moveToStatus($request, $assigned, $actor, $note ?: 'Request assigned to '.$assignee->full_name.'.');
                }
            }
        });

        $this->audit->log(
            event: 'adm_request.assigned',
            subject: $request,
            oldValues: ['assigned_to' => $previousAssignee],
            newValues: ['assigned_to' => $assignee->id],
            description: $actor->username.' assigned '.$request->reference.' to '.$assignee->username,
        );

        $request->loadMissing(['requester']);
        $this->notifications->admRequestAssigned($request, $actor, $assignee);

        return $request;
    }

    /**
     * Moves a request to another status. Resolved, closed and rejected need a
     * note, which becomes the resolution summary shown to the employee.
     */
    public function updateStatus(AdmRequest $request, User $actor, AdmRequestStatus $status, ?string $remarks = null): AdmRequest
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
            event: 'adm_request.status_changed',
            subject: $request,
            oldValues: ['status_id' => $from?->id],
            newValues: ['status_id' => $status->id],
            description: $actor->username.' changed '.$request->reference.' to '.$status->name,
        );

        $request->load(['requester', 'assignee', 'status', 'assignments']);

        if ($status->is_terminal) {
            $this->notifications->admRequestClosed($request, $actor);
        } else {
            $this->notifications->admRequestStatusChanged($request, $actor, $from);
        }

        return $request;
    }

    /**
     * Marks the request resolved and asks the employee to confirm — same as IT.
     */
    public function resolve(AdmRequest $request, User $actor, string $summary, bool $close = false): AdmRequest
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

        $resolved = AdmRequestStatus::query()->where('slug', 'resolved')->first();

        if (! $resolved) {
            throw new RuntimeException('The Administration request statuses have not been seeded.');
        }

        $request->loadMissing('status');
        $this->assertOpen($request, 'resolution_summary');
        $this->cctv->assertApprovedForResolution($request);
        $request = $this->claimIfUnassigned($request, $actor);

        $fromId = $request->status_id;

        $request = DB::transaction(function () use ($request, $actor, $resolved, $summary) {
            $request = AdmRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing('status');

            $this->applyOutcomeFields($request, $actor, $resolved, $summary);
            $this->moveToStatus($request, $resolved, $actor, $summary);

            return $request->fresh() ?? $request;
        });

        $this->audit->log(
            event: 'adm_request.resolved',
            subject: $request,
            oldValues: ['status_id' => $fromId],
            newValues: ['status' => 'resolved'],
            description: $actor->username.' resolved '.$request->reference,
        );

        $request->load(['requester', 'assignee', 'status', 'assignments', 'resolver']);
        $this->notifications->admRequestResolved($request, $actor);
        $this->notifications->admVerificationRequired($request, $actor);

        return $request;
    }

    /**
     * Employee confirms the fix and closes the request.
     */
    public function confirmResolution(AdmRequest $request, User $actor): AdmRequest
    {
        $closed = AdmRequestStatus::query()->where('slug', 'closed')->first();

        if (! $closed) {
            throw new RuntimeException('The Administration request statuses have not been seeded.');
        }

        $updated = DB::transaction(function () use ($request, $actor, $closed) {
            $request = AdmRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing(['status', 'assignments']);

            if ($request->status?->is_terminal || $request->status?->slug === 'closed') {
                return $request;
            }

            if (! $request->isAwaitingVerification()) {
                throw ValidationException::withMessages([
                    'decision' => ['This resolution can no longer be confirmed.'],
                ]);
            }

            // Employee confirmation must never fail because the desk was still
            // team-assigned — collapse to the primary assignee, then close.
            $this->collapseToPrimaryAssignee($request);

            $request->forceFill([
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);
            $this->moveToStatus($request, $closed, $actor, 'Employee confirmed the resolution.');

            $this->audit->log(
                event: 'adm_request.verified',
                subject: $request,
                newValues: ['status' => 'closed', 'verified_by' => $actor->id],
                description: $actor->username.' confirmed and closed '.$request->reference,
            );

            $this->audit->log(
                event: 'adm_request.closed',
                subject: $request,
                newValues: ['status' => 'closed', 'closed_by' => $actor->id],
                description: $actor->username.' closed '.$request->reference,
            );

            return $request->fresh() ?? $request;
        });

        $updated->load(['requester', 'assignee', 'status', 'assignments']);
        $this->notifications->admRequestClosed($updated, $actor);

        return $updated;
    }

    /**
     * Employee rejects the fix so Administration can keep working.
     */
    public function rejectResolution(AdmRequest $request, User $actor): AdmRequest
    {
        $inProgress = AdmRequestStatus::query()->where('slug', 'in-progress')->first();

        if (! $inProgress) {
            throw new RuntimeException('The Administration request statuses have not been seeded.');
        }

        $updated = DB::transaction(function () use ($request, $actor, $inProgress) {
            $request = AdmRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
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
                event: 'adm_request.verification_rejected',
                subject: $request,
                newValues: ['status' => 'in-progress', 'rejected_by' => $actor->id],
                description: $actor->username.' rejected the resolution for '.$request->reference,
            );

            return $request->fresh() ?? $request;
        });

        $updated->load(['requester', 'assignee', 'status', 'assignments']);
        $this->notifications->admRequestStatusChanged($updated, $actor);

        return $updated;
    }

    /**
     * Staff close as if the employee confirmed — used when confirmation is unavailable.
     */
    public function closeAsVerified(AdmRequest $request, User $actor): AdmRequest
    {
        $closed = AdmRequestStatus::query()->where('slug', 'closed')->first();

        if (! $closed) {
            throw new RuntimeException('The Administration request statuses have not been seeded.');
        }

        $updated = DB::transaction(function () use ($request, $actor, $closed) {
            $request = AdmRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing(['status', 'requester', 'assignments']);

            if ($request->status?->is_terminal || $request->status?->slug === 'closed') {
                throw ValidationException::withMessages([
                    'status_id' => ['This request is already closed.'],
                ]);
            }

            $request->assertIndividualAssigneeForClose();
            $this->cctv->assertApprovedForResolution($request);

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
                'resolution_summary' => $request->resolution_summary ?: 'Resolved by Administration.',
                'closed_by' => $requesterId,
                'closed_at' => $now,
            ]);
            $this->moveToStatus($request, $closed, $actor, 'Closed as verified by Administration on behalf of the employee.');

            $this->audit->log(
                event: 'adm_request.verified',
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
        $this->notifications->admRequestClosed($updated, $actor);

        return $updated;
    }

    private function claimIfUnassigned(AdmRequest $request, User $actor): AdmRequest
    {
        $request->loadMissing('assignments');

        if ($request->hasIndividualAssignee()) {
            return $request;
        }

        if ($actor->isAssignableAdmAgent()) {
            return $this->assign($request, $actor, $actor);
        }

        throw ValidationException::withMessages([
            'assigned_to' => ['Assign this request to a specific Administration staff member before resolving it.'],
        ]);
    }

    /**
     * Keep the primary assignee and release any leftover team assignments so
     * employee confirmation / close can proceed.
     */
    private function collapseToPrimaryAssignee(AdmRequest $request): void
    {
        $request->loadMissing('assignments');

        if ($request->hasIndividualAssignee()) {
            return;
        }

        if ($request->assigned_to === null) {
            throw ValidationException::withMessages([
                'assigned_to' => ['This request has no assignee, so it cannot be closed yet.'],
            ]);
        }

        $request->assignments()
            ->current()
            ->where('assigned_to', '!=', $request->assigned_to)
            ->update(['released_at' => now()]);

        $request->unsetRelation('assignments');
        $request->load('assignments');
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private function defaultStatus(): AdmRequestStatus
    {
        $status = AdmRequestStatus::query()->active()->where('is_default', true)->first()
            ?? AdmRequestStatus::query()->active()->where('slug', 'submitted')->first();

        if (! $status) {
            throw new RuntimeException('The default Administration request status has not been seeded.');
        }

        return $status;
    }

    /**
     * @param  array{
     *     category_id: int,
     *     request_type_id: int,
     *     subject: string,
     *     description: string,
     *     required_date?: ?string,
     *     company_name?: ?string,
     *     employee_id?: ?string,
     *     employee_name?: ?string,
     *     department_name?: ?string,
     *     designation?: ?string,
     *     contact_phone?: ?string
     * }  $data
     */
    private function insertWithUniqueReference(
        User $user,
        array $data,
        AdmCategory $category,
        AdmRequestType $type,
        AdmRequestStatus $status,
    ): AdmRequest {
        $attempts = 0;
        $attributes = $user->getAttributes();

        while (true) {
            $attempts++;

            try {
                return AdmRequest::query()->create([
                    'reference' => $this->references->next(),
                    'requester_id' => $user->id,
                    'employee_id' => (string) (($data['employee_id'] ?? null) ?: (($attributes['employee_id'] ?? null) ?: $user->username)),
                    'employee_name' => (string) (($data['employee_name'] ?? null) ?: $user->full_name),
                    'department_name' => ($data['department_name'] ?? null) ?: $user->department?->name,
                    'designation' => ($data['designation'] ?? null) ?: ($attributes['job_title'] ?? null),
                    'contact_phone' => ($data['contact_phone'] ?? null) ?: ($attributes['phone'] ?? null),
                    'branch_id' => $attributes['branch_id'] ?? null,
                    'department_id' => $attributes['department_id'] ?? null,
                    'adm_category_id' => $category->id,
                    'adm_request_type_id' => $type->id,
                    'status_id' => $status->id,
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'required_date' => $data['required_date'] ?? null,
                    'company_name' => $data['company_name'] ?? null,
                    'details' => $this->cleanDetails($data['details'] ?? null),
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
    private function resolveDueAt(AdmCategory $category, AdmRequestType $type): ?Carbon
    {
        $hours = AdmSlaSetting::query()
            ->where('is_active', true)
            ->where('adm_request_type_id', $type->id)
            ->value('target_hours');

        $hours ??= AdmSlaSetting::query()
            ->where('is_active', true)
            ->where('adm_category_id', $category->id)
            ->whereNull('adm_request_type_id')
            ->value('target_hours');

        $hours ??= $type->sla_hours ?? $category->default_sla_hours;

        return $hours !== null && (int) $hours > 0 ? now()->addHours((int) $hours) : null;
    }

    /**
     * Category-specific fields (e.g. CCTV location and footage window): flat
     * string map only, empty values dropped.
     *
     * @return array<string, string>|null
     */
    private function cleanDetails(mixed $details): ?array
    {
        if (! is_array($details)) {
            return null;
        }

        $clean = [];

        foreach ($details as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text !== '') {
                $clean[mb_substr($key, 0, 60)] = mb_substr($text, 0, 500);
            }
        }

        return $clean === [] ? null : array_slice($clean, 0, 20, true);
    }

    private function assertOpen(AdmRequest $request, string $field): void
    {
        if ($request->status?->is_terminal) {
            throw ValidationException::withMessages([
                $field => ['This request is already closed and can no longer be changed.'],
            ]);
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        return $note === '' ? null : $note;
    }

    /**
     * Stamps resolved/closed bookkeeping before the status itself is moved.
     */
    private function applyOutcomeFields(AdmRequest $request, User $actor, AdmRequestStatus $status, ?string $note): void
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
            case 'cancelled':
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
    private function moveToStatus(AdmRequest $request, AdmRequestStatus $status, User $actor, ?string $remarks): void
    {
        $from = $request->status;

        $request->forceFill(['status_id' => $status->id])->save();
        $request->setRelation('status', $status);

        $this->recordHistory($request, $from, $status, $actor, $remarks);
    }

    private function recordHistory(AdmRequest $request, ?AdmRequestStatus $from, AdmRequestStatus $to, ?User $actor, ?string $remarks): AdmRequestStatusHistory
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
     * @return array<int, AdmRequestAttachment>
     */
    public function addAttachments(AdmRequest $request, User $uploader, array $files): array
    {
        $request->loadMissing('status');
        $this->assertOpen($request, 'attachments');

        $stored = $this->storeAttachments($request, $uploader, $files);
        $request->touch();

        $this->audit->log(
            event: 'adm_request.attachments_added',
            subject: $request,
            newValues: ['count' => count($stored)],
            description: $uploader->username.' attached files to '.$request->reference,
        );

        return $stored;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, AdmRequestAttachment>
     */
    private function storeAttachments(AdmRequest $request, User $uploader, array $files): array
    {
        $max = (int) config('administrationhelpdesk.attachments.max_per_request');

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

    private function storeAttachment(AdmRequest $request, User $uploader, UploadedFile $file): AdmRequestAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $allowed = (array) config('administrationhelpdesk.attachments.allowed_extensions');
        $maxKilobytes = (int) config('administrationhelpdesk.attachments.max_kilobytes');

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

        $disk = (string) config('administrationhelpdesk.attachments.disk');
        $directory = trim((string) config('administrationhelpdesk.attachments.directory'), '/').'/'.$request->id;
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

    public function downloadAttachment(AdmRequestAttachment $attachment): StreamedResponse
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
