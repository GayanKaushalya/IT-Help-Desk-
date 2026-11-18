<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmRequestAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public attachment metadata. Storage path and disk stay hidden.
 *
 * @mixin AdmRequestAttachment
 */
class AdmAttachmentResource extends JsonResource
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
            'download_url' => '/administration/requests/'.$this->adm_request_id.'/attachments/'.$this->id,
        ];
    }
}
