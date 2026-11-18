<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\AdmRequestType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdmRequestType */
class AdmRequestTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adm_category_id' => $this->adm_category_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'sla_hours' => $this->sla_hours,
            'sort_order' => $this->sort_order,
        ];
    }
}
