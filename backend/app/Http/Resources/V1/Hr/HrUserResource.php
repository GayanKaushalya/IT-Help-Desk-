<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Hr;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal identity for HR lists (assignee, requester, comment author).
 *
 * @mixin User
 */
class HrUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'is_hr_staff' => $this->resource->isHrStaff(),
        ];
    }
}
