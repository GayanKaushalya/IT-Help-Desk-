<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrCategory */
class HrCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_confidential' => $this->is_confidential,
            'default_sla_hours' => $this->default_sla_hours,
            'sort_order' => $this->sort_order,
            'types' => HrRequestTypeResource::collection($this->whenLoaded('requestTypes')),
        ];
    }
}
