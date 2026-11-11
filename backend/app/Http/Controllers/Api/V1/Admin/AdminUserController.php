<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexUserRequest;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\V1\AdminUserResource;
use App\Http\Resources\V1\AuditLogResource;
use App\Http\Resources\V1\TicketResource;
use App\Models\User;
use App\Services\AdminUserQueryService;
use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserQueryService $query,
        private readonly AdminUserService $users,
    ) {}

    public function index(IndexUserRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            AdminUserResource::collection($this->query->paginate($request->filters())),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->users->create($request->user(), $request->payload());

        return ApiResponse::created(
            $this->userPayload($result['user'], $result['temporary_password']),
            $result['temporary_password']
                ? 'The account has been created. Give the temporary password to the user once.'
                : 'The account has been created.',
        );
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->cannot('view', $user)) {
            throw new AuthorizationException;
        }

        $user->loadMissing(['role', 'branch', 'department']);

        $payload = (new AdminUserResource($user))->resolve();
        $payload['ticket_statistics'] = $this->query->ticketStatistics($user);
        $payload['recent_tickets'] = TicketResource::collection($this->query->recentTickets($user))->resolve();

        if ($request->user()?->hasPermission('audit.view')) {
            $payload['audit_logs'] = AuditLogResource::collection($this->query->auditHistory($user))->resolve();
        }

        return ApiResponse::success($payload);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->users->update($request->user(), $user, $request->payload());

        return ApiResponse::success(
            (new AdminUserResource($updated))->resolve(),
            'The account has been updated.',
        );
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->cannot('activate', $user)) {
            throw new AuthorizationException;
        }

        $updated = $this->users->activate($request->user(), $user);

        return ApiResponse::success(
            (new AdminUserResource($updated))->resolve(),
            'The account has been activated.',
        );
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->cannot('deactivate', $user)) {
            throw new AuthorizationException;
        }

        $updated = $this->users->deactivate($request->user(), $user);

        return ApiResponse::success(
            (new AdminUserResource($updated))->resolve(),
            'The account has been deactivated.',
        );
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->cannot('resetPassword', $user)) {
            throw new AuthorizationException;
        }

        $result = $this->users->resetPassword($request->user(), $user);

        return ApiResponse::success(
            $this->userPayload($result['user'], $result['temporary_password']),
            'A temporary password has been generated. It will not be shown again.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user, ?string $temporaryPassword = null): array
    {
        $data = (new AdminUserResource($user->loadMissing(['role', 'branch', 'department'])))->resolve();

        if ($temporaryPassword !== null) {
            $data['temporary_password'] = $temporaryPassword;
        }

        return $data;
    }
}
