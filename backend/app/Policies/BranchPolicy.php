<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('branches.manage');
    }

    public function view(User $actor, Branch $branch): bool
    {
        return $actor->hasPermission('branches.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('branches.manage');
    }

    public function update(User $actor, Branch $branch): bool
    {
        return $actor->hasPermission('branches.manage');
    }

    public function activate(User $actor, Branch $branch): bool
    {
        return $actor->hasPermission('branches.manage');
    }

    public function deactivate(User $actor, Branch $branch): bool
    {
        return $actor->hasPermission('branches.manage');
    }
}
