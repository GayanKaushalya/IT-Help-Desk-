<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\IndexAdmRequestRequest;
use App\Http\Requests\Api\V1\Administration\StoreAdmRequestRequest;
use App\Http\Requests\Api\V1\Administration\VerifyAdmRequestRequest;
use App\Http\Resources\V1\Administration\AdmRequestResource;
use App\Models\AdmRequest;
use App\Services\Administration\AdmQueryService;
use App\Services\Administration\AdmRequestService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmRequestController extends Controller
{
    public function __construct(
        private readonly AdmQueryService $requests,
        private readonly AdmRequestService $writer,
    ) {}

    /**
     * Administration requests the authenticated employee raised. The staff queue lives
     * under administration/staff and is never returned from this endpoint.
     */
    public function index(IndexAdmRequestRequest $request): JsonResponse
    {
        $page = $this->requests->paginateOwned($request->user(), $request->filters());

        return ApiResponse::paginated(AdmRequestResource::collection($page));
    }

    public function store(StoreAdmRequestRequest $request): JsonResponse
    {
        $created = $this->writer->create(
            $request->user(),
            $request->requestData(),
            $request->uploadedFiles(),
        );

        $created = $this->requests->loadShowRelations($created);

        return ApiResponse::created(
            (new AdmRequestResource($created))->resolve(),
            'Your Administration request has been submitted.',
        );
    }

    public function show(Request $request, string $admRequest): JsonResponse
    {
        $model = $this->requests->findVisible($admRequest);
        $this->ensureVisible($request, $model);

        return ApiResponse::success((new AdmRequestResource($model))->resolve());
    }

    /**
     * Track a request by its public reference (e.g. ADM-2026-000001).
     */
    public function track(Request $request, string $reference): JsonResponse
    {
        $model = $this->requests->findByReference($reference);
        $this->ensureVisible($request, $model);

        return ApiResponse::success((new AdmRequestResource($model))->resolve());
    }

    public function downloadAttachment(Request $request, string $admRequest, int $attachment): StreamedResponse
    {
        $model = $this->requests->findVisible($admRequest);
        $this->ensureVisible($request, $model);

        $file = $model->attachments->firstWhere('id', $attachment);

        if (! $file) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [(string) $attachment]);
        }

        return $this->writer->downloadAttachment($file);
    }

    public function verify(VerifyAdmRequestRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->requests->findVisible($admRequest);

        if ($request->user()?->cannot('verify', $model)) {
            throw new AuthorizationException;
        }

        $updated = $request->isConfirmed()
            ? $this->writer->confirmResolution($model, $request->user())
            : $this->writer->rejectResolution($model, $request->user());

        $payload = (new AdmRequestResource($this->requests->loadShowRelations($updated)))->resolve();

        $message = $request->isConfirmed()
            ? 'Thank you. The request has been closed.'
            : 'Thanks for letting us know. Administration will continue working on this request.';

        return ApiResponse::success($payload, $message);
    }

    /**
     * Requests the caller may not see answer 404 rather than 403, so
     * references cannot be probed.
     */
    private function ensureVisible(Request $request, AdmRequest $model): void
    {
        if ($request->user()?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [(string) $model->reference]);
        }
    }
}
