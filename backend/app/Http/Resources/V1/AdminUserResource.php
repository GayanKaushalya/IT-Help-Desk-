<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Directory payload for the administration area.
 *
 * Password, tokens and hashes are never included.
 *
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'employee_id' => $this->employee_id,
            'epf_number' => $this->epf_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'is_active' => $this->is_active,
            'is_approved' => $this->is_approved,
            'account_status' => $this->accountStatus(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'role' => new RoleResource($this->whenLoaded('role')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
        ];
    }

    private function accountStatus(): string
    {
        if (! $this->is_approved) {
            return 'pending';
        }

        return $this->is_active ? 'active' : 'inactive';
    }
}
