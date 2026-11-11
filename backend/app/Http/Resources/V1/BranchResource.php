<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $forAdmin = $request->routeIs('api.v1.admin.*');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->when($forAdmin, $this->address),
            'phone' => $this->when($forAdmin, $this->phone),
            'is_active' => $this->is_active,
            'sort_order' => $this->when($forAdmin, $this->sort_order),
            'created_at' => $this->when($forAdmin, $this->created_at?->toIso8601String()),
            'updated_at' => $this->when($forAdmin, $this->updated_at?->toIso8601String()),
        ];
    }
}
