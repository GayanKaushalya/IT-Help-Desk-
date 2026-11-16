<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr\HrStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hr\Staff\AssignHrRequestRequest;
use App\Http\Requests\Api\V1\Hr\Staff\IndexStaffHrRequestRequest;
use App\Http\Requests\Api\V1\Hr\Staff\ReferHrRequestRequest;
use App\Http\Requests\Api\V1\Hr\Staff\ResolveHrRequestRequest;
use App\Http\Requests\Api\V1\Hr\Staff\RespondHrReferralRequest;
use App\Http\Requests\Api\V1\Hr\Staff\StoreStaffHrCommentRequest;
use App\Http\Requests\Api\V1\Hr\Staff\UpdateHrRequestStatusRequest;
use App\Http\Resources\V1\Hr\HrCommentResource;
use App\Http\Resources\V1\Hr\HrReferralResource;
use App\Http\Resources\V1\Hr\HrStaffRequestResource;
use App\Models\HrDepartmentReferral;
use App\Models\HrRequest;
use App\Models\HrRequestStatus;
use App\Models\User;
use App\Services\Hr\HrRequestQueryService;
use App\Services\Hr\HrRequestService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrStaffRequestController extends Controller
{
    public function __construct(
        private readonly HrRequestQueryService $requests,
        private readonly HrRequestService $writer,
    ) {}

    public function index(IndexStaffHrRequestRequest $request): JsonResponse
    {
        $page = $this->requests->paginateForStaff($request->user(), $request->filters());

        return ApiResponse::paginated(HrStaffRequestResource::collection($page));
    }

    public function assigned(IndexStaffHrRequestRequest $request): JsonResponse
    {
        $page = $this->requests->paginateForStaff($request->user(), $request->filters(), true);

        return ApiResponse::paginated(HrStaffRequestResource::collection($page));
    }

    /**
     * HR desk agents who can be assigned requests (IT-style workload cards).
     */
    public function agents(Request $request): JsonResponse
    {
        if ($request->user()?->cannot('viewQueue', HrRequest::class)) {
            throw new AuthorizationException;
        }

        $agents = User::query()
            ->assignableHrAgents()
            ->with('role')
            ->withCount([
                'assignedHrRequests as total_assigned',
                'assignedHrRequests as open_requests' => fn ($query) => $query->open(),
            ])
            ->orderBy('full_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'role' => $user->role?->name,
                'is_hr_staff' => true,
                'open_requests' => (int) $user->open_requests,
                'total_assigned' => (int) $user->total_assigned,
            ]);

        return ApiResponse::success($agents);
    }

    public function show(Request $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);

        return ApiResponse::success($this->payload($model));
    }

    public function assign(AssignHrRequestRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);
        $assignee = User::query()->findOrFail((int) $request->validated('assigned_to'));

        if ($request->user()?->cannot('assign', [$model, $assignee])) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->assign($model, $request->user(), $assignee, $request->validated('note'));

        return ApiResponse::success($this->payload($updated), 'The request has been assigned.');
    }

    public function updateStatus(UpdateHrRequestStatusRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);

        if ($request->user()?->cannot('updateStatus', $model)) {
            throw new AuthorizationException;
        }

        $status = HrRequestStatus::query()->findOrFail((int) $request->validated('status_id'));

        // Finishing a request is a separate, more privileged action.
        if (in_array($status->slug, ['resolved', 'closed', 'rejected'], true)
            && $request->user()?->cannot('resolve', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->updateStatus($model, $request->user(), $status, $request->remarks());

        return ApiResponse::success($this->payload($updated), 'The request status has been updated.');
    }

    public function resolve(ResolveHrRequestRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);

        if ($request->user()?->cannot('resolve', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->resolve($model, $request->user(), $request->summary(), $request->shouldClose());

        return ApiResponse::success(
            $this->payload($updated),
            $request->shouldClose()
                ? 'The request has been resolved and closed.'
                : 'The request has been marked resolved. Waiting for the employee to confirm.',
        );
    }

    public function closeAsVerified(Request $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);

        if ($request->user()?->cannot('closeAsVerified', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->closeAsVerified($model, $request->user());

        return ApiResponse::success($this->payload($updated), 'The request has been closed as verified.');
    }

    public function refer(ReferHrRequestRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);

        if ($request->user()?->cannot('refer', $model)) {
            throw new AuthorizationException;
        }

        $assignedTo = $request->validated('assigned_to');

        $referral = $this->writer->createReferral(
            $model,
            $request->user(),
            (string) $request->validated('department'),
            $request->validated('brief'),
            $assignedTo !== null ? (int) $assignedTo : null,
        );

        return ApiResponse::created(
            (new HrReferralResource($referral))->resolve(),
            'The request has been referred.',
        );
    }

    public function respondToReferral(RespondHrReferralRequest $request, string $hrRequest, int $referral): JsonResponse
    {
        $model = $this->requests->findVisible($hrRequest, true);
        $target = HrDepartmentReferral::query()
            ->where('hr_request_id', $model->id)
            ->findOrFail($referral);

        if ($request->user()?->cannot('respondToReferral', [$model, $target])) {
            throw new AuthorizationException;
        }

        $answered = $this->writer->respondToReferral($target, $request->user(), $request->responseText());

        return ApiResponse::success(
            (new HrReferralResource($answered))->resolve(),
            'The referral response has been recorded.',
        );
    }

    public function comment(StoreStaffHrCommentRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $hrRequest);
        $user = $request->user();

        if ($request->isInternal()) {
            if ($user?->cannot('addInternalNote', $model)) {
                throw new AuthorizationException;
            }
        } elseif ($user?->cannot('comment', $model)) {
            throw new AuthorizationException;
        }

        $comment = $this->writer->addComment($model, $user, $request->messageBody(), $request->isInternal());

        return ApiResponse::created(
            (new HrCommentResource($comment))->resolve(),
            $request->isInternal() ? 'Internal note added.' : 'Message added to the request.',
        );
    }

    /**
     * Hidden requests (e.g. confidential complaints) answer 404, not 403.
     */
    private function findForStaff(Request $request, string $identifier): HrRequest
    {
        $model = $this->requests->findVisible($identifier, true);

        if ($request->user()?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(HrRequest::class, [$identifier]);
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HrRequest $request): array
    {
        return (new HrStaffRequestResource($this->requests->loadShowRelations($request, true)))->resolve();
    }
}
