<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\AuditLog;
use App\Support\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe audit row for authorised administrators.
 *
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'action' => AuditEvent::label((string) $this->event),
            'entity_type' => AuditEvent::entityKey($this->auditable_type, (string) $this->event),
            'entity_id' => $this->auditable_id,
            'description' => $this->description,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
            'actor' => $this->whenLoaded(
                'user',
                fn () => $this->user ? new UserSummaryResource($this->user) : null,
            ),
        ];
    }
}
