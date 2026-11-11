<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequestComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversation entry. `is_internal` is only exposed on staff routes, where
 * internal notes are returned; employee queries never load them.
 *
 * @mixin HrRequestComment
 */
class HrCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_system' => $this->is_system,
            'is_internal' => $this->when($request->routeIs('api.v1.hr.staff.*'), $this->is_internal),
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => new HrUserResource($this->whenLoaded('author')),
            'attachments' => HrAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
