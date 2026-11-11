<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexBranchRequest;
use App\Http\Requests\Api\V1\Admin\StoreBranchRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBranchRequest;
use App\Http\Resources\V1\BranchResource;
use App\Models\Branch;
use App\Services\AdminBranchService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBranchController extends Controller
{
    public function __construct(private readonly AdminBranchService $branches) {}

    public function index(IndexBranchRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            BranchResource::collection($this->branches->paginate($request->filters())),
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create($request->user(), $request->payload());

        return ApiResponse::created(
            (new BranchResource($branch))->resolve(),
            'The branch has been created.',
        );
    }

    public function show(Request $request, Branch $branch): JsonResponse
    {
        if ($request->user()?->cannot('view', $branch)) {
            throw new AuthorizationException;
        }

        return ApiResponse::success((new BranchResource($branch))->resolve());
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $updated = $this->branches->update($request->user(), $branch, $request->payload());

        return ApiResponse::success(
            (new BranchResource($updated))->resolve(),
            'The branch has been updated.',
        );
    }

    public function activate(Request $request, Branch $branch): JsonResponse
    {
        if ($request->user()?->cannot('activate', $branch)) {
            throw new AuthorizationException;
        }

        $updated = $this->branches->activate($request->user(), $branch);

        return ApiResponse::success(
            (new BranchResource($updated))->resolve(),
            'The branch has been activated.',
        );
    }

    public function deactivate(Request $request, Branch $branch): JsonResponse
    {
        if ($request->user()?->cannot('deactivate', $branch)) {
            throw new AuthorizationException;
        }

        $updated = $this->branches->deactivate($request->user(), $branch);

        return ApiResponse::success(
            (new BranchResource($updated))->resolve(),
            'The branch has been deactivated. It will no longer appear on registration.',
        );
    }
}
