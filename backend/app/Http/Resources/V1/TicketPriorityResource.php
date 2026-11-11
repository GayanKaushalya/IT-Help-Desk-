<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TicketPriority;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketPriority */
class TicketPriorityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'level' => $this->level,
            'color_token' => $this->color_token,
            'response_minutes' => $this->response_minutes,
            'resolution_minutes' => $this->resolution_minutes,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
