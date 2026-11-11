<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hr\StoreHrCommentRequest;
use App\Http\Resources\V1\Hr\HrCommentResource;
use App\Models\HrRequest;
use App\Services\Hr\HrRequestQueryService;
use App\Services\Hr\HrRequestService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class HrRequestCommentController extends Controller
{
    public function __construct(
        private readonly HrRequestQueryService $requests,
        private readonly HrRequestService $writer,
    ) {}

    /**
     * Employee reply on one of their own requests. Employees can never
     * create internal notes through this endpoint.
     */
    public function store(StoreHrCommentRequest $request, string $hrRequest): JsonResponse
    {
        $model = $this->requests->findVisible($hrRequest);
        $user = $request->user();

        if ($user?->cannot('view', $model)) {
            throw (new ModelNotFoundException)->setModel(HrRequest::class, [(string) $model->reference]);
        }

        if ($user?->cannot('comment', $model)) {
            throw new AuthorizationException;
        }

        $comment = $this->writer->addComment($model, $user, $request->messageBody(), false);

        return ApiResponse::created(
            (new HrCommentResource($comment))->resolve(),
            'Your message has been added to the request.',
        );
    }
}
