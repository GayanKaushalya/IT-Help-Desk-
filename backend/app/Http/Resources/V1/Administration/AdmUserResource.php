<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Administration;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal identity for Administration lists (assignee, requester, comment author).
 *
 * @mixin User
 */
class AdmUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'is_administration_staff' => $this->resource->isAdministrationStaff(),
        ];
    }
}
