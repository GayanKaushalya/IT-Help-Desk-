<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tickets\VerifyTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Services\TicketQueryService;
use App\Services\TicketVerificationService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class TicketVerificationController extends Controller
{
    public function __construct(
        private readonly TicketQueryService $tickets,
        private readonly TicketVerificationService $verifications,
    ) {}

    public function store(VerifyTicketRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findVisible($ticket);

        if ($request->user()?->cannot('verify', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->verifications->respond(
            $model,
            $request->user(),
            $request->decision(),
        );

        try {
            $payload = (new TicketResource($this->tickets->loadShowRelations($updated)))->resolve();
        } catch (\Throwable $exception) {
            report($exception);
            $updated->loadMissing('status');
            $payload = [
                'id' => $updated->id,
                'reference' => $updated->reference,
                'awaiting_verification' => false,
                'status' => [
                    'slug' => $updated->status?->slug ?? ($request->isConfirmed() ? 'closed' : 'awaiting-user'),
                    'name' => $updated->status?->name ?? ($request->isConfirmed() ? 'Closed' : 'Awaiting User'),
                    'is_closed' => (bool) ($updated->status?->is_closed ?? $request->isConfirmed()),
                ],
                'verification' => [
                    'status' => $request->isConfirmed() ? 'verified' : 'cancelled',
                    'is_blocked' => false,
                ],
            ];
        }

        $message = $request->isConfirmed()
            ? 'Thank you. The ticket has been closed.'
            : 'Thanks for letting us know. IT support will continue working on this ticket.';

        return ApiResponse::success($payload, $message);
    }
}
