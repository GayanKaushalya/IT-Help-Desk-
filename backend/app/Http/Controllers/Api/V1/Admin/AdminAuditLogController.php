<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexAuditLogRequest;
use App\Http\Resources\V1\AuditLogResource;
use App\Services\AdminAuditQueryService;
use App\Support\ApiResponse;
use App\Support\AuditEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function __construct(private readonly AdminAuditQueryService $query) {}

    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            AuditLogResource::collection($this->query->paginate($request->filters())),
        );
    }

    public function lookups(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('audit.view')) {
            throw new AuthorizationException;
        }

        return ApiResponse::success([
            'events' => AuditEvent::options(),
            'entity_types' => [
                ['value' => 'user', 'label' => 'User'],
                ['value' => 'ticket', 'label' => 'Ticket'],
                ['value' => 'branch', 'label' => 'Branch'],
                ['value' => 'auth', 'label' => 'Authentication'],
                ['value' => 'report', 'label' => 'Report'],
                ['value' => 'category', 'label' => 'Category'],
                ['value' => 'priority', 'label' => 'Priority'],
                ['value' => 'status', 'label' => 'Status'],
                ['value' => 'settings', 'label' => 'Settings'],
                ['value' => 'knowledge', 'label' => 'Knowledge'],
            ],
        ]);
    }
}
