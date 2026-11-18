<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\V1\Administration\Staff\Registers\DriverRequest;
use App\Http\Requests\Api\V1\Administration\Staff\Registers\IndexRegisterRequest;
use App\Http\Resources\V1\Administration\DriverResource;
use App\Models\Driver;
use App\Services\Administration\DriverService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends AdmRegisterController
{
    public function __construct(private readonly DriverService $drivers) {}

    public function index(IndexRegisterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(DriverResource::collection($this->drivers->paginate($request->filters())));
    }

    public function store(DriverRequest $request): JsonResponse
    {
        $driver = $this->drivers->create($request->user(), $request->validated());

        return ApiResponse::created((new DriverResource($driver))->resolve(), 'Driver added.');
    }

    public function show(Request $request, Driver $driver): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success((new DriverResource($driver))->resolve());
    }

    public function update(DriverRequest $request, Driver $driver): JsonResponse
    {
        $updated = $this->drivers->update($driver, $request->user(), $request->validated());

        return ApiResponse::success((new DriverResource($updated))->resolve(), 'Driver updated.');
    }

    public function destroy(Request $request, Driver $driver): JsonResponse
    {
        $this->authorizeManage($request);
        $this->drivers->delete($driver, $request->user());

        return ApiResponse::success(null, 'Driver removed.');
    }
}
