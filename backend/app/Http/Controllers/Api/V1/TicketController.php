<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tickets\IndexTicketRequest;
use App\Http\Requests\Api\V1\Tickets\StoreTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Services\TicketQueryService;
use App\Services\TicketService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketQueryService $tickets,
        private readonly TicketService $ticketWriter,
    ) {}

    /**
     * Tickets the authenticated employee raised. Staff queues use a
     * separate module and are never returned from this endpoint.
     */
    public function index(IndexTicketRequest $request): JsonResponse
    {
        $page = $this->tickets->paginateOwned($request->user(), $request->filters());

        return ApiResponse::paginated(TicketResource::collection($page));
    }

    /**
     * Raise a new IT support request as the authenticated employee.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketWriter->create(
            $request->user(),
            $request->ticketData(),
            $request->uploadedFiles(),
        );

        return ApiResponse::created(
            (new TicketResource($ticket))->resolve(),
            'Your IT support request has been submitted.',
        );
    }

    /**
     * A single ticket the caller is allowed to see.
     *
     * Employees may only open tickets they raised. Changing the id or
     * reference in the URL cannot be used to read another requester's ticket.
     */
    public function show(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findVisible($ticket);

        if ($request->user()?->cannot('view', $model)) {
            throw new AuthorizationException;
        }

        return ApiResponse::success((new TicketResource($model))->resolve());
    }
}
