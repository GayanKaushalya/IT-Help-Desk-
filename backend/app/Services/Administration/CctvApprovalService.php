<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\AdmRequest;
use App\Models\CctvAccessLog;
use App\Models\CctvApproval;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Validation\ValidationException;

/**
 * CCTV footage and surveillance requests need an explicit approval before the
 * desk can resolve them. Every decision and access is written to
 * cctv_access_logs.
 */
class CctvApprovalService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Creates the pending approval record for a CCTV request (idempotent).
     */
    public function ensureRecord(AdmRequest $request, ?User $requestedBy = null): ?CctvApproval
    {
        if (! $request->isCctv()) {
            return null;
        }

        $approval = CctvApproval::query()->where('adm_request_id', $request->id)->first();

        if ($approval) {
            return $approval;
        }

        $approval = CctvApproval::query()->create([
            'adm_request_id' => $request->id,
            'status' => CctvApproval::PENDING,
            'requested_by' => $requestedBy?->id ?? $request->requester_id,
        ]);

        $this->log($request, $approval, $requestedBy, 'approval_requested', 'CCTV approval requested.');

        return $approval;
    }

    /**
     * Staff may only resolve a CCTV request once an approver has approved it.
     */
    public function assertApprovedForResolution(AdmRequest $request): void
    {
        if (! $request->isCctv()) {
            return;
        }

        $approval = CctvApproval::query()->where('adm_request_id', $request->id)->first();

        if (! $approval || ! $approval->isApproved()) {
            throw ValidationException::withMessages([
                'cctv_approval' => [
                    $approval?->status === CctvApproval::REJECTED
                        ? 'The CCTV approval for this request was rejected, so it cannot be resolved as fulfilled.'
                        : 'This CCTV request needs an approved CCTV approval before it can be resolved.',
                ],
            ]);
        }
    }

    /**
     * Approve or reject a CCTV request. Authorization is the caller's job.
     */
    public function decide(AdmRequest $request, User $actor, string $decision, ?string $notes = null): CctvApproval
    {
        if (! in_array($decision, [CctvApproval::APPROVED, CctvApproval::REJECTED], true)) {
            throw ValidationException::withMessages(['decision' => ['Choose approve or reject.']]);
        }

        $request->loadMissing(['category', 'status']);

        if (! $request->isCctv()) {
            throw ValidationException::withMessages([
                'cctv_approval' => ['Only CCTV & Surveillance requests use CCTV approvals.'],
            ]);
        }

        if ($request->status?->is_terminal) {
            throw ValidationException::withMessages([
                'cctv_approval' => ['This request is already closed and can no longer be approved.'],
            ]);
        }

        $notes = $notes === null ? null : trim($notes);
        $notes = $notes === '' ? null : $notes;

        if ($decision === CctvApproval::REJECTED && $notes === null) {
            throw ValidationException::withMessages([
                'decision_notes' => ['Add a short reason for rejecting the CCTV request.'],
            ]);
        }

        $approval = DB::transaction(function () use ($request, $actor, $decision, $notes): CctvApproval {
            $approval = CctvApproval::query()
                ->where('adm_request_id', $request->id)
                ->lockForUpdate()
                ->first()
                ?? $this->ensureRecord($request, $actor);

            $approval->forceFill([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->log($request, $approval, $actor, $decision, $notes);

            return $approval;
        });

        $this->audit->log(
            event: 'adm_request.cctv_'.$decision,
            subject: $request,
            newValues: ['cctv_approval_id' => $approval->id, 'status' => $decision],
            description: $actor->username.' '.$decision.' the CCTV request '.$request->reference,
        );

        $request->loadMissing(['requester', 'assignee']);
        $label = $decision === CctvApproval::APPROVED ? 'approved' : 'rejected';

        foreach ([$request->requester, $request->assignee] as $recipient) {
            if ($recipient && (int) $recipient->id !== (int) $actor->id) {
                $this->notifications->notify(
                    $recipient,
                    'adm.cctv.'.$label,
                    'CCTV request '.$label,
                    $request->reference.' was '.$label.' by '.$actor->full_name.'.',
                    ['module' => 'administration', 'adm_request_id' => (int) $request->id, 'reference' => $request->reference],
                );
            }
        }

        return $approval->load('decider');
    }

    /**
     * @return array{approval: ?CctvApproval, logs: \Illuminate\Support\Collection<int, CctvAccessLog>}
     */
    public function summary(AdmRequest $request): array
    {
        return [
            'approval' => CctvApproval::query()->with('decider')->where('adm_request_id', $request->id)->first(),
            'logs' => CctvAccessLog::query()
                ->with('user')
                ->where('adm_request_id', $request->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ];
    }

    public function log(AdmRequest $request, ?CctvApproval $approval, ?User $user, string $action, ?string $notes = null): ?CctvAccessLog
    {
        $userId = $user?->id ?? $request->requester_id;

        return CctvAccessLog::query()->create([
            'adm_request_id' => $request->id,
            'cctv_approval_id' => $approval?->id,
            'user_id' => $userId,
            'action' => $action,
            'notes' => $notes !== null ? mb_substr($notes, 0, 2000) : null,
            'ip_address' => RequestFacade::ip(),
        ]);
    }
}
