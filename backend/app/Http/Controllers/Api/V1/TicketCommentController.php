<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tickets\StoreTicketCommentRequest;
use App\Http\Resources\V1\TicketCommentResource;
use App\Services\TicketCommentService;
use App\Services\TicketQueryService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class TicketCommentController extends Controller
{
    public function __construct(
        private readonly TicketQueryService $tickets,
        private readonly TicketCommentService $comments,
    ) {}

    /**
     * Post a public reply. Internal notes cannot be created from this route.
     */
    public function store(StoreTicketCommentRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findVisible($ticket);

        if ($request->user()?->cannot('comment', $model)) {
            throw new AuthorizationException;
        }

        $comment = $this->comments->addPublicComment(
            $model,
            $request->user(),
            $request->messageBody(),
        );

        return ApiResponse::created(
            (new TicketCommentResource($comment))->resolve(),
            'Your message has been added to the ticket.',
        );
    }
}
