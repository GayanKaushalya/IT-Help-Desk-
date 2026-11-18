<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Administration\StoreAdmCommentRequest;
use App\Http\Resources\V1\Administration\AdmCommentResource;
use App\Models\AdmRequest;
use App\Services\Administration\AdmQueryService;
use App\Services\Administration\AdmRequestService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class AdmRequestCommentController extends Controller
{
    public function __construct(
        private readonly AdmQueryService $requests,
        private readonly AdmRequestService $writer,
    ) {}

    /**
     * Employee reply on one of their own requests. Employees can never
     * create internal notes through this endpoint.
     */
    public function store(StoreAdmCommentRequest $request, string $admRequest): JsonResponse
    {
        $model = $this->requests->findVisible($admRequest);
        $user = $request->user();

        if ($user?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [(string) $model->reference]);
        }

        if ($user?->cannot('comment', $model)) {
            throw new AuthorizationException;
        }

        $comment = $this->writer->addComment($model, $user, $request->messageBody(), false);

        return ApiResponse::created(
            (new AdmCommentResource($comment))->resolve(),
            'Your message has been added to the request.',
        );
    }
}
