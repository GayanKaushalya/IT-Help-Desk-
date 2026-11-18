<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\V1\Administration\Staff\Registers\IndexRegisterRequest;
use App\Http\Requests\Api\V1\Administration\Staff\Registers\VehicleAllocationRequest;
use App\Http\Resources\V1\Administration\VehicleAllocationResource;
use App\Models\VehicleAllocation;
use App\Services\Administration\VehicleAllocationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleAllocationController extends AdmRegisterController
{
    public function __construct(private readonly VehicleAllocationService $allocations) {}

    public function index(IndexRegisterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            VehicleAllocationResource::collection($this->allocations->paginate($request->filters())),
        );
    }

    public function store(VehicleAllocationRequest $request): JsonResponse
    {
        $allocation = $this->allocations->create($request->user(), $request->validated());

        return ApiResponse::created((new VehicleAllocationResource($allocation))->resolve(), 'Vehicle allocated.');
    }

    public function update(VehicleAllocationRequest $request, VehicleAllocation $allocation): JsonResponse
    {
        $updated = $this->allocations->update($allocation, $request->user(), $request->validated());

        return ApiResponse::success((new VehicleAllocationResource($updated))->resolve(), 'Allocation updated.');
    }

    public function cancel(Request $request, VehicleAllocation $allocation): JsonResponse
    {
        $this->authorizeManage($request);

        $cancelled = $this->allocations->cancel($allocation, $request->user());

        return ApiResponse::success((new VehicleAllocationResource($cancelled))->resolve(), 'Allocation cancelled.');
    }
}
