<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\Staff\DecideCctvRequest;
use App\Models\AdmRequest;
use App\Models\CctvApproval;
use App\Services\Administration\AdmQueryService;
use App\Services\Administration\CctvApprovalService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmCctvApprovalController extends Controller
{
    public function __construct(
        private readonly AdmQueryService $requests,
        private readonly CctvApprovalService $cctv,
    ) {}

    public function show(Request $request, string $admRequest): JsonResponse
    {
        $model = $this->find($request, $admRequest);

        return ApiResponse::success($this->payload($model, $request));
    }

    public function approve(DecideCctvRequest $request, string $admRequest): JsonResponse
    {
        return $this->decide($request, $admRequest, CctvApproval::APPROVED, 'The CCTV request has been approved.');
    }

    public function reject(DecideCctvRequest $request, string $admRequest): JsonResponse
    {
        return $this->decide($request, $admRequest, CctvApproval::REJECTED, 'The CCTV request has been rejected.');
    }

    private function decide(DecideCctvRequest $request, string $identifier, string $decision, string $message): JsonResponse
    {
        $model = $this->find($request, $identifier);

        if ($request->user()?->cannot('decideCctv', $model)) {
            throw new AuthorizationException;
        }

        $this->cctv->decide($model, $request->user(), $decision, $request->notes());

        return ApiResponse::success($this->payload($model, $request), $message);
    }

    private function find(Request $request, string $identifier): AdmRequest
    {
        $model = $this->requests->findVisible($identifier, true);

        if ($request->user()?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [$identifier]);
        }

        if (! $model->isCctv()) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [$identifier]);
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AdmRequest $model, Request $request): array
    {
        $summary = $this->cctv->summary($model);
        $approval = $summary['approval'];

        return [
            'approval' => $approval ? [
                'id' => $approval->id,
                'status' => $approval->status,
                'decided_at' => $approval->decided_at?->toIso8601String(),
                'decision_notes' => $approval->decision_notes,
                'decided_by' => $approval->decider ? [
                    'id' => $approval->decider->id,
                    'full_name' => $approval->decider->full_name,
                ] : null,
            ] : null,
            'can_decide' => (bool) $request->user()?->can('decideCctv', $model),
            'access_logs' => $summary['logs']->map(fn ($log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'notes' => $log->notes,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user ? ['id' => $log->user->id, 'full_name' => $log->user->full_name] : null,
            ])->values()->all(),
        ];
    }
}
