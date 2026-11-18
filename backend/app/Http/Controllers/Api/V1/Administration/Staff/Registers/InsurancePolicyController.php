<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\V1\Administration\Staff\Registers\IndexRegisterRequest;
use App\Http\Requests\Api\V1\Administration\Staff\Registers\InsuranceDocumentRequest;
use App\Http\Requests\Api\V1\Administration\Staff\Registers\InsurancePolicyRequest;
use App\Http\Resources\V1\Administration\InsurancePolicyResource;
use App\Models\InsurancePolicy;
use App\Services\Administration\InsurancePolicyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InsurancePolicyController extends AdmRegisterController
{
    public function __construct(private readonly InsurancePolicyService $policies) {}

    public function index(IndexRegisterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            InsurancePolicyResource::collection($this->policies->paginate($request->filters())),
        );
    }

    public function store(InsurancePolicyRequest $request): JsonResponse
    {
        $policy = $this->policies->create($request->user(), $request->validated());

        return ApiResponse::created((new InsurancePolicyResource($policy))->resolve(), 'Insurance policy added.');
    }

    public function show(Request $request, InsurancePolicy $policy): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success((new InsurancePolicyResource($policy->load('responsibleOfficer')))->resolve());
    }

    public function update(InsurancePolicyRequest $request, InsurancePolicy $policy): JsonResponse
    {
        $updated = $this->policies->update($policy, $request->user(), $request->validated());

        return ApiResponse::success((new InsurancePolicyResource($updated))->resolve(), 'Insurance policy updated.');
    }

    public function destroy(Request $request, InsurancePolicy $policy): JsonResponse
    {
        $this->authorizeManage($request);
        $this->policies->delete($policy, $request->user());

        return ApiResponse::success(null, 'Insurance policy removed.');
    }

    public function history(Request $request, InsurancePolicy $policy): JsonResponse
    {
        $this->authorizeView($request);

        return ApiResponse::success($this->policies->history($policy)->map(fn ($entry) => [
            'id' => $entry->id,
            'event' => $entry->event,
            'notes' => $entry->notes,
            'changed_at' => $entry->changed_at?->toIso8601String(),
            'changed_by' => $entry->actor ? ['id' => $entry->actor->id, 'full_name' => $entry->actor->full_name] : null,
        ])->values()->all());
    }

    public function uploadDocument(InsuranceDocumentRequest $request, InsurancePolicy $policy): JsonResponse
    {
        $updated = $this->policies->storeDocument($policy, $request->user(), $request->file('document'));

        return ApiResponse::success((new InsurancePolicyResource($updated))->resolve(), 'Policy document uploaded.');
    }

    public function downloadDocument(Request $request, InsurancePolicy $policy): StreamedResponse
    {
        $this->authorizeView($request);

        return $this->policies->downloadDocument($policy);
    }
}
