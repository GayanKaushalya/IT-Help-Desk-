<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr\HrStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hr\Staff\IndexHrAuditLogRequest;
use App\Http\Resources\V1\AuditLogResource;
use App\Services\Hr\HrAuditQueryService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAuditLogController extends Controller
{
    public function __construct(private readonly HrAuditQueryService $query) {}

    public function index(IndexHrAuditLogRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            AuditLogResource::collection($this->query->paginate($request->filters())),
        );
    }

    public function lookups(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('hr.reports.view')) {
            throw new AuthorizationException;
        }

        return ApiResponse::success($this->query->lookups());
    }
}
