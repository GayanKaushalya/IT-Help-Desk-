<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\HrRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HrRequestStatus */
class HrStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'color_token' => $this->color_token,
            'is_open' => $this->is_open,
            'is_terminal' => $this->is_terminal,
            'sort_order' => $this->sort_order,
        ];
    }
}
