<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketStatus */
class TicketStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'color_token' => $this->color_token,
            'is_open' => $this->is_open,
            'is_resolved' => $this->is_resolved,
            'is_closed' => $this->is_closed,
            'requires_verification' => $this->requires_verification,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
