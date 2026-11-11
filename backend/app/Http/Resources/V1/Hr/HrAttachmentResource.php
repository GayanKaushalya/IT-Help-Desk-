<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequestAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public attachment metadata. Storage path and disk stay hidden.
 *
 * @mixin HrRequestAttachment
 */
class HrAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toIso8601String(),
            // Relative to the API base (/api/v1).
            'download_url' => '/hr/requests/'.$this->hr_request_id.'/attachments/'.$this->id,
        ];
    }
}
