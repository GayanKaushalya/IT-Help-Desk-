<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BranchResource;
use App\Http\Resources\V1\DepartmentResource;
use App\Http\Resources\V1\RoleResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDirectoryController extends Controller
{
    public function lookups(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor?->hasPermission('users.view') && ! $actor?->hasPermission('branches.manage')) {
            throw new AuthorizationException;
        }

        return ApiResponse::success([
            'roles' => RoleResource::collection(
                Role::query()->where('is_active', true)->orderBy('level')->orderBy('name')->get()
            ),
            'branches' => BranchResource::collection(Branch::query()->ordered()->get()),
            'departments' => DepartmentResource::collection(Department::query()->active()->ordered()->get()),
        ]);
    }
}
