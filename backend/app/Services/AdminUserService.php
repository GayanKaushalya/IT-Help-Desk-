<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and maintains directory accounts for authorised administrators.
 *
 * Plaintext passwords exist only in memory at issue time. Only a hash is stored.
 */
class AdminUserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, temporary_password: string|null}
     */
    public function create(User $actor, array $data): array
    {
        $role = Role::query()->findOrFail((int) $data['role_id']);
        $this->assertAssignableRole($actor, $role);

        $plain = isset($data['password']) && is_string($data['password']) && $data['password'] !== ''
            ? $data['password']
            : $this->generateTemporaryPassword();
        $generated = ! isset($data['password']) || $data['password'] === '';

        $user = DB::transaction(function () use ($actor, $data, $role, $plain): User {
            $user = new User;
            $user->forceFill([
                'full_name' => $data['full_name'],
                'username' => $data['username'],
                'employee_id' => $data['employee_id'],
                'epf_number' => $data['epf_number'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'role_id' => $role->id,
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'] ?? null,
                'password' => $plain,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_approved' => true,
                'approved_at' => now(),
                'password_changed_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'user.created',
                subject: $user,
                newValues: [
                    'username' => $user->username,
                    'role_id' => $user->role_id,
                    'branch_id' => $user->branch_id,
                    'is_active' => $user->is_active,
                ],
                description: $actor->username.' created account '.$user->username,
            );

            return $user->fresh(['role', 'branch', 'department']) ?? $user;
        });

        return [
            'user' => $user,
            'temporary_password' => $generated ? $plain : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, temporary_password: string|null}
     */
    public function update(User $actor, User $user, array $data): array
    {
        $this->assertCanMutate($actor, $user);

        $issuedPassword = null;
        if (isset($data['password']) && is_string($data['password']) && $data['password'] !== '') {
            if ($this->isSelf($actor, $user)) {
                throw ValidationException::withMessages([
                    'password' => ['Use your own profile to change your password.'],
                ]);
            }

            if (! $actor->hasPermission('users.reset_password')) {
                throw ValidationException::withMessages([
                    'password' => ['You are not allowed to set passwords for other users.'],
                ]);
            }

            $issuedPassword = $data['password'];
            unset($data['password']);
        }

        if (isset($data['role_id'])) {
            $role = Role::query()->findOrFail((int) $data['role_id']);
            $this->assertAssignableRole($actor, $role, $user);

            if ($this->isSelf($actor, $user) && (int) $role->id !== (int) $user->role_id) {
                throw ValidationException::withMessages([
                    'role_id' => ['You cannot change your own role.'],
                ]);
            }

            $data['role_id'] = $role->id;
        }

        if ($this->isSelf($actor, $user) && array_key_exists('is_active', $data)
            && (bool) $data['is_active'] !== (bool) $user->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ['You cannot change your own account status.'],
            ]);
        }

        $user->fill($data);

        if ($user->isDirty('is_approved') && $user->is_approved && ! $user->approved_at) {
            $user->approved_at = now();
        }

        if ($issuedPassword !== null) {
            $user->forceFill([
                'password' => $issuedPassword,
                'password_changed_at' => now(),
            ]);
        }

        $original = $user->getOriginal();
        $dirty = $user->getDirty();
        // Never put the hash into audit newValues.
        unset($dirty['password']);
        $user->save();

        if ($issuedPassword !== null) {
            $user->tokens()->delete();
            $this->audit->log(
                event: 'user.password_reset',
                subject: $user,
                newValues: ['password_changed_at' => now()->toIso8601String()],
                description: $actor->username.' set a new password for '.$user->username,
            );
        }

        if ($dirty !== []) {
            $this->audit->log(
                event: 'user.updated',
                subject: $user,
                oldValues: array_intersect_key($original, $dirty),
                newValues: $dirty,
                description: $actor->username.' updated account '.$user->username,
            );
        }

        return [
            'user' => $user->fresh(['role', 'branch', 'department']) ?? $user,
            'temporary_password' => $issuedPassword,
        ];
    }

    public function activate(User $actor, User $user): User
    {
        $this->assertCanMutate($actor, $user);

        $user->forceFill([
            'is_active' => true,
            'is_approved' => true,
            'approved_at' => $user->approved_at ?? now(),
        ])->save();

        $this->audit->log(
            event: 'user.activated',
            subject: $user,
            newValues: ['is_active' => true],
            description: $actor->username.' activated '.$user->username,
        );

        return $user->fresh(['role', 'branch', 'department']) ?? $user;
    }

    public function deactivate(User $actor, User $user): User
    {
        $this->assertCanMutate($actor, $user);

        if ($this->isSelf($actor, $user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your own account.'],
            ]);
        }

        if ($user->isSuperAdmin()) {
            $remaining = User::query()
                ->where('is_active', true)
                ->where('is_approved', true)
                ->where('id', '!=', $user->id)
                ->whereHas('role', fn ($role) => $role->where('slug', RoleSlug::SuperAdmin->value))
                ->count();

            if ($remaining === 0) {
                throw ValidationException::withMessages([
                    'user' => ['The last Super Admin account cannot be deactivated.'],
                ]);
            }
        }

        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();

        $this->audit->log(
            event: 'user.deactivated',
            subject: $user,
            newValues: ['is_active' => false],
            description: $actor->username.' deactivated '.$user->username,
        );

        return $user->fresh(['role', 'branch', 'department']) ?? $user;
    }

    /**
     * @return array{user: User, temporary_password: string}
     */
    public function resetPassword(User $actor, User $user): array
    {
        $this->assertCanMutate($actor, $user);

        if ($this->isSelf($actor, $user)) {
            throw ValidationException::withMessages([
                'user' => ['Use your own profile to change your password.'],
            ]);
        }

        $plain = $this->generateTemporaryPassword();

        $user->forceFill([
            'password' => $plain,
            'password_changed_at' => now(),
        ])->save();

        $user->tokens()->delete();

        $this->audit->log(
            event: 'user.password_reset',
            subject: $user,
            newValues: ['password_changed_at' => now()->toIso8601String()],
            description: $actor->username.' reset the password for '.$user->username,
        );

        return [
            'user' => $user->fresh(['role', 'branch', 'department']) ?? $user,
            'temporary_password' => $plain,
        ];
    }

    private function assertCanMutate(User $actor, User $user): void
    {
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Only a Super Admin can change a Super Admin account.'],
            ]);
        }
    }

    private function assertAssignableRole(User $actor, Role $role, ?User $target = null): void
    {
        if ($target?->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role_id' => ['Only a Super Admin can change a Super Admin account.'],
            ]);
        }

        if ($role->slug === RoleSlug::SuperAdmin->value && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role_id' => ['Only a Super Admin can assign the Super Admin role.'],
            ]);
        }

        if ($actor->isSuperAdmin()) {
            return;
        }

        $actor->loadMissing('role');
        $actorLevel = (int) ($actor->role?->level ?? 0);

        // Administrators may assign Administrator (same level). Higher roles stay blocked.
        if ((int) $role->level > $actorLevel) {
            throw ValidationException::withMessages([
                'role_id' => ['You can only assign a role at or below your own level.'],
            ]);
        }
    }

    private function isSelf(User $actor, User $user): bool
    {
        return (int) $actor->id === (int) $user->id;
    }

    private function generateTemporaryPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%&*?';
        $all = $upper.$lower.$digits.$symbols;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = 4; $i < 14; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
