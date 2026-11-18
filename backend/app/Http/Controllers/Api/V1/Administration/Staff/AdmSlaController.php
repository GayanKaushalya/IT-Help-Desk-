<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\Staff\UpdateAdmSlaRequest;
use App\Models\AdmCategory;
use App\Models\AdmRequestType;
use App\Services\Administration\AdmSlaService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmSlaController extends Controller
{
    public function __construct(private readonly AdmSlaService $sla) {}

    public function index(Request $request): JsonResponse
    {
        if (! ($request->user()?->hasPermission('adm.sla.manage') ?? false)) {
            throw new AuthorizationException;
        }

        return ApiResponse::success($this->sla->catalogue());
    }

    public function updateCategory(UpdateAdmSlaRequest $request, int $category): JsonResponse
    {
        $model = AdmCategory::query()->findOrFail($category);

        $this->sla->updateCategory(
            $model,
            (int) $request->validated('target_hours'),
            (bool) $request->validated('apply_to_types', false),
            $request->user(),
        );

        return ApiResponse::success($this->sla->catalogue(), 'Category SLA updated.');
    }

    public function updateType(UpdateAdmSlaRequest $request, int $type): JsonResponse
    {
        $model = AdmRequestType::query()->findOrFail($type);

        $this->sla->updateType($model, (int) $request->validated('target_hours'), $request->user());

        return ApiResponse::success($this->sla->catalogue(), 'Request type SLA updated.');
    }
}
