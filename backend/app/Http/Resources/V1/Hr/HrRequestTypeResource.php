<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequestType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrRequestType */
class HrRequestTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hr_category_id' => $this->hr_category_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'sla_hours' => $this->sla_hours,
            'default_referral_department' => $this->when(
                $request->routeIs('api.v1.hr.staff.*'),
                $this->default_referral_department,
            ),
            'sort_order' => $this->sort_order,
        ];
    }
}
