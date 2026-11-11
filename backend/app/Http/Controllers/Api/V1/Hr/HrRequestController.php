<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hr\IndexHrRequestRequest;
use App\Http\Requests\Api\V1\Hr\StoreHrRequestRequest;
use App\Http\Requests\Api\V1\Hr\VerifyHrRequestRequest;
use App\Http\Resources\V1\Hr\HrRequestResource;
use App\Models\HrRequest;
use App\Services\Hr\HrRequestQueryService;
use App\Services\Hr\HrRequestService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrRequestController extends Controller
{
    public function __construct(
        private readonly HrRequestQueryService $requests,
        private readonly HrRequestService $writer,
    ) {}

    /**
     * HR requests the authenticated employee raised. The staff queue lives
     * under hr/staff and is never returned from this endpoint.
     */
    public function index(IndexHrRequestRequest $request): JsonResponse
    {
        $page = $this->requests->paginateOwned($request->user(), $request->filters());

        return ApiResponse::paginated(HrRequestResource::collection($page));
    }

    public function store(StoreHrRequestRequest $request): JsonResponse
    {
        $created = $this->writer->create(
            $request->user(),
            $request->requestData(),
            $request->uploadedFiles(),
        );

        $created = $this->requests->loadShowRelations($created);

        return ApiResponse::created(
            (new HrRequestResource($created))->resolve(),
            'Your HR request has been submitted.',
        );
    }

    public function show(Request $request, string $hrRequest): JsonResponse
    {
        $model = $this->requests->findVisible($hrRequest);
        $this->ensureVisible($request, $model);

        return ApiResponse::success((new HrRequestResource($model))->resolve());
    }

    /**
     * Track a request by its public reference (e.g. HR-2026-000001).
     */
    public function track(Request $request, string $reference): JsonResponse
    {
        $model = $this->requests->findByReference($reference);
        $this->ensureVisible($request, $model);

        return ApiResponse::success((new HrRequestResource($model))->resolve());
    }

    public function downloadAttachment(Request $request, string $hrRequest, int $attachment): StreamedResponse
    {
        $model = $this->requests->findVisible($hrRequest);
        $this->ensureVisible($request, $model);

        $file = $model->attachments->firstWhere('id', $attachment);

        if (! $file) {
            throw (new ModelNotFoundException)->setModel(HrRequest::class, [(string) $attachment]);
        }

        return $this->writer->downloadAttachment($file);
    }

    public function verify(VerifyHrRequestRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->requests->findVisible($hrRequest);

        if ($request->user()?->cannot('verify', $model)) {
            throw new AuthorizationException;
        }

        $updated = $request->isConfirmed()
            ? $this->writer->confirmResolution($model, $request->user())
            : $this->writer->rejectResolution($model, $request->user());

        $payload = (new HrRequestResource($this->requests->loadShowRelations($updated)))->resolve();

        $message = $request->isConfirmed()
            ? 'Thank you. The request has been closed.'
            : 'Thanks for letting us know. HR will continue working on this request.';

        return ApiResponse::success($payload, $message);
    }

    /**
     * Requests the caller may not see answer 404 rather than 403, so
     * references (especially confidential complaints) cannot be probed.
     */
    private function ensureVisible(Request $request, HrRequest $model): void
    {
        if ($request->user()?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(HrRequest::class, [(string) $model->reference]);
        }
    }
}
