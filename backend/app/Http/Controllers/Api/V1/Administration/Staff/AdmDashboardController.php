<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Administration\AdmStaffRequestResource;
use App\Models\AdmRequest;
use App\Services\Administration\AdmDashboardService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmDashboardController extends Controller
{
    public function __construct(private readonly AdmDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user?->cannot('viewQueue', AdmRequest::class)) {
            throw new AuthorizationException;
        }

        $overview = $this->dashboard->overview($user);

        return ApiResponse::success([
            'statistics' => $overview['statistics'],
            'by_category' => $overview['by_category'],
            'by_status' => $overview['by_status'],
            'recent_requests' => AdmStaffRequestResource::collection($overview['recent_requests'])->resolve(),
        ]);
    }
}
