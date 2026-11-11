<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr\HrStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hr\Staff\UpdateHrSlaRequest;
use App\Models\HrCategory;
use App\Models\HrRequestType;
use App\Services\Hr\HrSlaService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrSlaController extends Controller
{
    public function __construct(private readonly HrSlaService $sla) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSla($request);

        return ApiResponse::success($this->sla->catalogue());
    }

    public function updateCategory(UpdateHrSlaRequest $request, int $category): JsonResponse
    {
        $this->authorizeSla($request);

        $model = HrCategory::query()->findOrFail($category);
        $this->sla->updateCategory($model, $request->validated(), $request->user());

        return ApiResponse::success($this->sla->catalogue(), 'Category SLA updated.');
    }

    public function updateType(UpdateHrSlaRequest $request, int $type): JsonResponse
    {
        $this->authorizeSla($request);

        $model = HrRequestType::query()->findOrFail($type);
        $this->sla->updateType($model, $request->validated(), $request->user());

        return ApiResponse::success($this->sla->catalogue(), 'Request type SLA updated.');
    }

    private function authorizeSla(Request $request): void
    {
        if (! $request->user()?->hasPermission('hr.sla.manage')) {
            throw new AuthorizationException;
        }
    }
}
