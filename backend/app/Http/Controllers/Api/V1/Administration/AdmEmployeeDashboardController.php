<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Administration\AdmRequestResource;
use App\Models\AdmRequest;
use App\Services\Administration\AdmEmployeeDashboardService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmEmployeeDashboardController extends Controller
{
    public function __construct(private readonly AdmEmployeeDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        if ($request->user()?->cannot('viewAny', AdmRequest::class)) {
            throw new AuthorizationException;
        }

        $overview = $this->dashboard->overview($request->user());

        return ApiResponse::success([
            'statistics' => $overview['statistics'],
            'recent_requests' => AdmRequestResource::collection($overview['recent_requests'])->resolve(),
        ]);
    }
}
