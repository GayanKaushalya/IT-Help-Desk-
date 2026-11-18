<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\V1\Administration\Staff\Registers\FuelCardRequest;
use App\Http\Requests\Api\V1\Administration\Staff\Registers\IndexRegisterRequest;
use App\Http\Resources\V1\Administration\FuelCardResource;
use App\Models\FuelCard;
use App\Services\Administration\FuelCardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelCardController extends AdmRegisterController
{
    public function __construct(private readonly FuelCardService $cards) {}

    public function index(IndexRegisterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(FuelCardResource::collection($this->cards->paginate($request->filters())));
    }

    public function store(FuelCardRequest $request): JsonResponse
    {
        $card = $this->cards->create($request->user(), $request->validated());

        return ApiResponse::created((new FuelCardResource($card))->resolve(), 'Fuel card added.');
    }

    public function show(Request $request, FuelCard $fuelCard): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success((new FuelCardResource($fuelCard->load(['vehicle', 'driver', 'assignedUser'])))->resolve());
    }

    public function update(FuelCardRequest $request, FuelCard $fuelCard): JsonResponse
    {
        $updated = $this->cards->update($fuelCard, $request->user(), $request->validated());

        return ApiResponse::success((new FuelCardResource($updated))->resolve(), 'Fuel card updated.');
    }

    public function destroy(Request $request, FuelCard $fuelCard): JsonResponse
    {
        $this->authorizeManage($request);
        $this->cards->delete($fuelCard, $request->user());

        return ApiResponse::success(null, 'Fuel card removed.');
    }

    public function history(Request $request, FuelCard $fuelCard): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success($this->cards->history($fuelCard)->map(fn ($entry) => [
            'id' => $entry->id,
            'event' => $entry->event,
            'notes' => $entry->notes,
            'changed_at' => $entry->changed_at?->toIso8601String(),
            'changed_by' => $entry->actor ? ['id' => $entry->actor->id, 'full_name' => $entry->actor->full_name] : null,
        ])->values()->all());
    }
}
