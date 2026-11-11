<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr\HrStaff;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Hr\HrStaffRequestResource;
use App\Models\HrRequest;
use App\Services\Hr\HrDashboardService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    public function __construct(private readonly HrDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user?->cannot('viewQueue', HrRequest::class)) {
            throw new AuthorizationException;
        }

        $overview = $this->dashboard->overview($user);

        return ApiResponse::success([
            'statistics' => $overview['statistics'],
            'by_category' => $overview['by_category'],
            'by_status' => $overview['by_status'],
            'recent_requests' => HrStaffRequestResource::collection($overview['recent_requests'])->resolve(),
        ]);
    }
}
