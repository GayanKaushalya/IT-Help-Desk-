<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TicketAttachmentResource;
use App\Services\TicketAttachmentService;
use App\Services\TicketQueryService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly TicketQueryService $tickets,
        private readonly TicketAttachmentService $attachments,
    ) {}

    /**
     * Stream a file only when the caller is allowed to view the parent ticket.
     */
    public function show(Request $request, string $ticket, string $attachment): StreamedResponse
    {
        $model = $this->tickets->findVisible($ticket);

        if ($request->user()?->cannot('view', $model)) {
            throw new AuthorizationException;
        }

        $file = $model->attachments()->whereKey($attachment)->firstOrFail();

        return $this->attachments->download($file);
    }

    public function store(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findVisible($ticket);

        if ($request->user()?->cannot('attach', $model)) {
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
}
