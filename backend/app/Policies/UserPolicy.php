<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Directory access is permission-based. IT Support only sees what their
 * role was explicitly granted; Super Admin bypasses via hasPermission().
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.update');
    }

    public function activate(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.deactivate');
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.deactivate');
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.reset_password');
    }
}
