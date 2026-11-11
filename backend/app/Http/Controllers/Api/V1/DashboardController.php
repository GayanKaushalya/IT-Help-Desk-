<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Statistics and recent tickets for the signed-in requester.
     */
    public function show(Request $request): JsonResponse
    {
        if ($request->user()?->cannot('viewAny', Ticket::class)) {
            throw new AuthorizationException;
        }

        $overview = $this->dashboard->overview($request->user());

        return ApiResponse::success([
            'statistics' => $overview['statistics'],
            'recent_tickets' => TicketResource::collection($overview['recent_tickets'])->resolve(),
        ]);
    }
}
