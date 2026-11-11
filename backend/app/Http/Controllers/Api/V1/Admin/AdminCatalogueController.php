<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\StorePriorityRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePriorityRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStatusRequest;
use App\Http\Resources\V1\TicketCategoryResource;
use App\Http\Resources\V1\TicketPriorityResource;
use App\Http\Resources\V1\TicketStatusResource;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Services\CatalogueService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCatalogueController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (
            ! $user?->hasPermission('categories.manage')
            && ! $user?->hasPermission('priorities.manage')
            && ! $user?->hasPermission('statuses.manage')
        ) {
            throw new AuthorizationException;
        }

        $overview = $this->catalogue->overview();

        return ApiResponse::success([
            'categories' => TicketCategoryResource::collection($overview['categories'])->resolve(),
            'priorities' => TicketPriorityResource::collection($overview['priorities'])->resolve(),
            'statuses' => TicketStatusResource::collection($overview['statuses'])->resolve(),
        ]);
    }

    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->catalogue->createCategory($request->user(), $request->payload());

        return ApiResponse::created(
            (new TicketCategoryResource($category))->resolve(),
            'The category has been created.',
        );
    }

    public function updateCategory(UpdateCategoryRequest $request, TicketCategory $ticket_category): JsonResponse
    {
        $updated = $this->catalogue->updateCategory($request->user(), $ticket_category, $request->payload());

        return ApiResponse::success(
            (new TicketCategoryResource($updated))->resolve(),
            'The category has been updated.',
        );
    }

    public function storePriority(StorePriorityRequest $request): JsonResponse
    {
        $priority = $this->catalogue->createPriority($request->user(), $request->payload());

        return ApiResponse::created(
            (new TicketPriorityResource($priority))->resolve(),
            'The priority has been created.',
        );
    }

    public function updatePriority(UpdatePriorityRequest $request, TicketPriority $ticket_priority): JsonResponse
    {
        $updated = $this->catalogue->updatePriority($request->user(), $ticket_priority, $request->payload());

        return ApiResponse::success(
            (new TicketPriorityResource($updated))->resolve(),
            'The priority has been updated.',
        );
    }

    public function updateStatus(UpdateStatusRequest $request, TicketStatus $ticket_status): JsonResponse
    {
        $updated = $this->catalogue->updateStatus($request->user(), $ticket_status, $request->payload());

        return ApiResponse::success(
            (new TicketStatusResource($updated))->resolve(),
            'The status has been updated.',
        );
    }
}
