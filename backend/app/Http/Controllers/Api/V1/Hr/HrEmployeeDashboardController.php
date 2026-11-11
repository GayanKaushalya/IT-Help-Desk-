<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Hr\HrRequestResource;
use App\Models\HrRequest;
use App\Services\Hr\HrEmployeeDashboardService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeDashboardController extends Controller
{
    public function __construct(private readonly HrEmployeeDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        if ($request->user()?->cannot('viewAny', HrRequest::class)) {
            throw new AuthorizationException;
        }

        $overview = $this->dashboard->overview($request->user());

        return ApiResponse::success([
            'statistics' => $overview['statistics'],
            'recent_requests' => HrRequestResource::collection($overview['recent_requests'])->resolve(),
        ]);
    }
}
