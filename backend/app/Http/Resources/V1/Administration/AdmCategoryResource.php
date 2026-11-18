<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdmCategory */
class AdmCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'default_sla_hours' => $this->default_sla_hours,
            'sort_order' => $this->sort_order,
            'types' => AdmRequestTypeResource::collection($this->whenLoaded('requestTypes')),
        ];
    }
}
