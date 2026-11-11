<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{email:?string, phone:?string, job_title:?string}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->refresh();
        $before = $user->only(['email', 'phone', 'job_title']);

        $user->fill([
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
        ])->save();

        $user = $user->fresh() ?? $user;
        $user->load('role.permissions', 'branch', 'department');

        $this->audit->log(
            event: 'user.profile_updated',
            subject: $user,
            oldValues: $before,
            newValues: $user->only(['email', 'phone', 'job_title']),
            description: $user->username.' updated their profile',
        );

        return $user;
    }

    public function changePassword(User $user, string $current, string $next): User
    {
        if (! Hash::check($current, $user->password)) {
            throw new ApiException(
                'The current password is incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'INVALID_CURRENT_PASSWORD',
            );
        }

        $user->password = $next;
        $user->password_changed_at = now();
        $user->save();

        $this->audit->log(
            event: 'user.password_changed',
            subject: $user,
            description: $user->username.' changed their password',
        );

        $user = $user->fresh() ?? $user;

        return $user->load('role.permissions', 'branch', 'department');
    }
}
