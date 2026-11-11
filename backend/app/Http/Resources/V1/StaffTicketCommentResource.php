<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversation entry for IT staff, including the internal-note flag.
 *
 * @mixin TicketComment
 */
class StaffTicketCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'is_system' => $this->is_system,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'author' => new UserSummaryResource($this->whenLoaded('author')),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
