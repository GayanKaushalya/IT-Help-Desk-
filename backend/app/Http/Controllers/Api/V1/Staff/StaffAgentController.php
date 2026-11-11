<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAgentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('tickets.view_any')) {
            throw new AuthorizationException;
        }

        $agents = User::query()
            ->staff()
            ->with('role')
            ->withCount([
                'assignedTickets as total_assigned',
                'assignedTickets as open_tickets' => fn ($query) => $query->open(),
            ])
            ->orderBy('full_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'role' => $user->role?->name,
                'is_staff' => true,
                'open_tickets' => (int) $user->open_tickets,
                'total_assigned' => (int) $user->total_assigned,
            ]);

        return ApiResponse::success($agents);
    }
}
