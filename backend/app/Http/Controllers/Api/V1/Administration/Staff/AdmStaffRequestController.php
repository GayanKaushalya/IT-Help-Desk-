<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\Staff\AssignAdmRequestRequest;
use App\Http\Requests\Api\V1\Administration\Staff\AttachAdmRequestFilesRequest;
use App\Http\Requests\Api\V1\Administration\Staff\IndexStaffAdmRequestRequest;
use App\Http\Requests\Api\V1\Administration\Staff\ResolveAdmRequestRequest;
use App\Http\Requests\Api\V1\Administration\Staff\StoreStaffAdmCommentRequest;
use App\Http\Requests\Api\V1\Administration\Staff\UpdateAdmRequestStatusRequest;
use App\Http\Resources\V1\Administration\AdmAttachmentResource;
use App\Http\Resources\V1\Administration\AdmCommentResource;
use App\Http\Resources\V1\Administration\AdmStaffRequestResource;
use App\Models\AdmRequest;
use App\Models\AdmRequestStatus;
use App\Models\User;
use App\Services\Administration\AdmQueryService;
use App\Services\Administration\AdmRequestService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmStaffRequestController extends Controller
{
    /** Statuses that finish a request and need the more privileged resolve permission. */
    private const FINISHING_STATUSES = ['resolved', 'closed', 'rejected', 'cancelled'];

    public function __construct(
        private readonly AdmQueryService $requests,
        private readonly AdmRequestService $writer,
    ) {}

    public function index(IndexStaffAdmRequestRequest $request): JsonResponse
    {
        $page = $this->requests->paginateForStaff($request->user(), $request->filters());

        return ApiResponse::paginated(AdmStaffRequestResource::collection($page));
    }

    public function assigned(IndexStaffAdmRequestRequest $request): JsonResponse
    {
        $page = $this->requests->paginateForStaff($request->user(), $request->filters(), true);

        return ApiResponse::paginated(AdmStaffRequestResource::collection($page));
    }

    /**
     * Administration desk agents who can be assigned requests (workload cards).
     */
    public function agents(Request $request): JsonResponse
    {
        if ($request->user()?->cannot('viewQueue', AdmRequest::class)) {
            throw new AuthorizationException;
        }

        $agents = User::query()
            ->assignableAdmAgents()
            ->with('role')
            ->withCount([
                'assignedAdmRequests as total_assigned',
                'assignedAdmRequests as open_requests' => fn ($query) => $query->open(),
            ])
            ->orderBy('full_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'role' => $user->role?->name,
                'is_administration_staff' => true,
                'open_requests' => (int) $user->open_requests,
                'total_assigned' => (int) $user->total_assigned,
            ]);

        return ApiResponse::success($agents);
    }

    public function show(Request $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);

        return ApiResponse::success($this->payload($model));
    }

    public function assign(AssignAdmRequestRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);
        $assignee = User::query()->findOrFail((int) $request->validated('assigned_to'));

        if ($request->user()?->cannot('assign', [$model, $assignee])) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->assign($model, $request->user(), $assignee, $request->validated('note'));

        return ApiResponse::success($this->payload($updated), 'The request has been assigned.');
    }

    public function updateStatus(UpdateAdmRequestStatusRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);

        if ($request->user()?->cannot('updateStatus', $model)) {
            throw new AuthorizationException;
        }

        $status = AdmRequestStatus::query()->findOrFail((int) $request->validated('status_id'));

        // Finishing a request is a separate, more privileged action.
        if (in_array($status->slug, self::FINISHING_STATUSES, true)
            && $request->user()?->cannot('resolve', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->updateStatus($model, $request->user(), $status, $request->remarks());

        return ApiResponse::success($this->payload($updated), 'The request status has been updated.');
    }

    public function resolve(ResolveAdmRequestRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);

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

    public function closeAsVerified(Request $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);

        if ($request->user()?->cannot('closeAsVerified', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->writer->closeAsVerified($model, $request->user());

        return ApiResponse::success($this->payload($updated), 'The request has been closed as verified.');
    }

    public function comment(StoreStaffAdmCommentRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);
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
            (new AdmCommentResource($comment))->resolve(),
            $request->isInternal() ? 'Internal note added.' : 'Message added to the request.',
        );
    }

    public function attach(AttachAdmRequestFilesRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->findForStaff($request, $admRequest);

        if ($request->user()?->cannot('comment', $model)) {
            throw new AuthorizationException;
        }

        $stored = $this->writer->addAttachments($model, $request->user(), $request->uploadedFiles());

        return ApiResponse::created(
            AdmAttachmentResource::collection(collect($stored))->resolve(),
            'Attachment uploaded.',
        );
    }

    private function findForStaff(Request $request, string $identifier): AdmRequest
    {
        $model = $this->requests->findVisible($identifier, true);

        if ($request->user()?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [$identifier]);
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AdmRequest $request): array
    {
        return (new AdmStaffRequestResource($this->requests->loadShowRelations($request, true)))->resolve();
    }
}
