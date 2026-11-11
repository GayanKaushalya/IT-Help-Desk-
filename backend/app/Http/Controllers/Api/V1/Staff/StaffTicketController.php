<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Staff\AssignTicketRequest;
use App\Http\Requests\Api\V1\Staff\ResolveTicketRequest;
use App\Http\Requests\Api\V1\Staff\StaffIndexTicketRequest;
use App\Http\Requests\Api\V1\Staff\StoreStaffCommentRequest;
use App\Http\Requests\Api\V1\Staff\UpdateTicketPriorityRequest;
use App\Http\Requests\Api\V1\Staff\UpdateTicketStatusRequest;
use App\Http\Resources\V1\StaffTicketCommentResource;
use App\Http\Resources\V1\StaffTicketResource;
use App\Http\Resources\V1\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\StaffTicketQueryService;
use App\Services\TicketAttachmentService;
use App\Services\TicketCommentService;
use App\Services\TicketWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class StaffTicketController extends Controller
{
    public function __construct(
        private readonly StaffTicketQueryService $tickets,
        private readonly TicketWorkflowService $workflow,
        private readonly TicketCommentService $comments,
        private readonly TicketAttachmentService $attachments,
    ) {}

    public function index(StaffIndexTicketRequest $request): JsonResponse
    {
        $page = $this->tickets->paginateQueue($request->filters());

        return ApiResponse::paginated(StaffTicketResource::collection($page));
    }

    public function assigned(StaffIndexTicketRequest $request): JsonResponse
    {
        $page = $this->tickets->paginateQueue($request->filters(), (int) $request->user()->id);

        return ApiResponse::paginated(StaffTicketResource::collection($page));
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('view', $model)) {
            throw new AuthorizationException;
        }

        return ApiResponse::success((new StaffTicketResource($model))->resolve());
    }

    public function assign(AssignTicketRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);
        $assignee = User::query()->findOrFail((int) $request->validated('assigned_to'));

        if ($request->user()?->cannot('assign', [$model, $assignee])) {
            throw new AuthorizationException;
        }

        $updated = $this->workflow->assign(
            $model,
            $request->user(),
            $assignee,
            $request->validated('note'),
        );

        return ApiResponse::success(
            (new StaffTicketResource($this->tickets->loadStaffRelations($updated)))->resolve(),
            'The ticket has been assigned.',
        );
    }

    public function updateStatus(UpdateTicketStatusRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('updateStatus', $model)) {
            throw new AuthorizationException;
        }

        $status = TicketStatus::query()->findOrFail((int) $request->validated('status_id'));
        $result = $this->workflow->changeStatus(
            $model,
            $request->user(),
            $status,
            $request->resolutionSummary(),
        );

        $payload = $this->staffPayload($result['ticket']);
        $awaiting = (bool) ($payload['awaiting_verification'] ?? false);

        return ApiResponse::success(
            $payload,
            $awaiting
                ? 'The ticket has been resolved and is awaiting the employee to confirm.'
                : 'The ticket status has been updated.',
        );
    }

    public function updatePriority(UpdateTicketPriorityRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('updatePriority', $model)) {
            throw new AuthorizationException;
        }

        $priority = TicketPriority::query()->findOrFail((int) $request->validated('priority_id'));
        $updated = $this->workflow->changePriority($model, $request->user(), $priority);

        return ApiResponse::success(
            (new StaffTicketResource($this->tickets->loadStaffRelations($updated)))->resolve(),
            'The ticket priority has been updated.',
        );
    }

    public function resolve(ResolveTicketRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('resolve', $model)) {
            throw new AuthorizationException;
        }

        $result = $this->workflow->resolve(
            $model,
            $request->user(),
            (string) $request->validated('resolution_summary'),
        );

        return ApiResponse::success(
            $this->staffPayload($result['ticket']),
            'The ticket has been resolved and is awaiting the employee to confirm.',
        );
    }

    public function clearResolution(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('resolve', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->workflow->clearResolution($model, $request->user());

        return ApiResponse::success(
            $this->staffPayload($updated),
            'The resolution was cleared. The ticket is back In Progress.',
        );
    }

    public function closeAsVerified(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('closeAsVerified', $model)) {
            throw new AuthorizationException;
        }

        $updated = $this->workflow->closeAsVerified($model, $request->user());

        return ApiResponse::success(
            $this->staffPayload($updated),
            'The ticket was closed as verified by the employee.',
        );
    }

    public function comment(StoreStaffCommentRequest $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);
        $user = $request->user();

        if ($request->isInternal()) {
            if ($user?->cannot('addInternalNote', $model)) {
                throw new AuthorizationException;
            }

            $comment = $this->comments->addInternalNote($model, $user, $request->messageBody());
        } else {
            if ($user?->cannot('comment', $model)) {
                throw new AuthorizationException;
            }

            $comment = $this->comments->addPublicComment($model, $user, $request->messageBody());
        }

        return ApiResponse::created(
            (new StaffTicketCommentResource($comment))->resolve(),
            $request->isInternal() ? 'Internal note added.' : 'Message added to the ticket.',
        );
    }

    public function attach(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findForStaff($ticket);

        if ($request->user()?->cannot('comment', $model)) {
            throw new AuthorizationException;
        }

        $request->validate([
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file'],
        ]);

        $files = array_values(array_filter(
            (array) $request->file('attachments', []),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        $stored = $this->attachments->storeMany($model, $request->user(), $files);

        return ApiResponse::created(
            TicketAttachmentResource::collection($stored)->resolve(),
            'Attachment uploaded.',
        );
    }

    private function staffPayload(Ticket $ticket, ?string $verificationCode = null): array
    {
        return (new StaffTicketResource($this->tickets->loadStaffRelations($ticket)))->resolve();
    }
}
