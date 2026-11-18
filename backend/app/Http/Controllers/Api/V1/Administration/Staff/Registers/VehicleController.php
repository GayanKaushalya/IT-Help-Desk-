<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\V1\Administration\Staff\Registers\IndexRegisterRequest;
use App\Http\Requests\Api\V1\Administration\Staff\Registers\VehicleRequest;
use App\Http\Resources\V1\Administration\VehicleResource;
use App\Models\Vehicle;
use App\Services\Administration\VehicleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends AdmRegisterController
{
    public function __construct(private readonly VehicleService $vehicles) {}

    public function index(IndexRegisterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(VehicleResource::collection($this->vehicles->paginate($request->filters())));
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        $vehicle = $this->vehicles->create($request->user(), $request->validated());

        return ApiResponse::created((new VehicleResource($vehicle))->resolve(), 'Vehicle added.');
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success((new VehicleResource($vehicle->load(['driver', 'insurancePolicy'])))->resolve());
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $updated = $this->vehicles->update($vehicle, $request->user(), $request->validated());

        return ApiResponse::success((new VehicleResource($updated))->resolve(), 'Vehicle updated.');
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeManage($request);
        $this->vehicles->delete($vehicle, $request->user());

        return ApiResponse::success(null, 'Vehicle removed.');
    }
}
