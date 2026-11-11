<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StaffTicketResource;
use App\Services\StaffDashboardService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function __construct(private readonly StaffDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('tickets.view_any')) {
            throw new AuthorizationException;
        }

        $overview = $this->dashboard->overview();

        return ApiResponse::success([
            'statistics' => $overview['statistics'],
            'charts' => $overview['charts'],
            'recent_tickets' => StaffTicketResource::collection($overview['recent_tickets'])->resolve(),
        ]);
    }
}
