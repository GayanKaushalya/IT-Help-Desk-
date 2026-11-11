<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketCategory */
class TicketCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'default_priority_id' => $this->default_priority_id,
            'is_active' => $this->is_active,
            'requires_item_details' => (bool) $this->requires_item_details,
            'sort_order' => $this->sort_order,
        ];
    }
}
