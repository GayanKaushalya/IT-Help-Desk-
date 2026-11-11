<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public attachment metadata. Storage path and disk stay hidden.
 *
 * @mixin TicketAttachment
 */
class TicketAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            // Relative to the API base (/api/v1) so the SPA proxy/auth client
            // can download without hitting APP_URL directly (CORS / LAN issues).
            'download_url' => '/tickets/'.$this->ticket_id.'/attachments/'.$this->id,
        ];
    }
}
