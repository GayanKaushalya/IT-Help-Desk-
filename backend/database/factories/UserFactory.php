<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** Known plain-text password for tests that need to sign in. */
    public const PASSWORD = 'Password@123';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'employee_id' => 'EMP-'.fake()->unique()->numberBetween(10000, 99999),
            'epf_number' => 'EPF-'.fake()->unique()->numberBetween(10000, 99999),
            'email' => null,
            'phone' => null,
            'job_title' => null,
            'password' => self::PASSWORD,
            'role_id' => fn () => $this->roleId(RoleSlug::Employee),
            'branch_id' => fn () => $this->branchId(),
            'department_id' => null,
            'is_active' => true,
            'is_approved' => true,
        ];
    }

    public function role(RoleSlug $role): static
    {
        return $this->state(fn () => ['role_id' => $this->roleId($role)]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn () => ['is_approved' => false]);
    }

    /**
     * Roles are seeded system data, so reuse the row when it already exists
     * rather than creating a duplicate slug.
     */
    private function roleId(RoleSlug $role): int
    {
        return Role::firstOrCreate(
            ['slug' => $role->value],
            ['name' => $role->label(), 'level' => $role->level(), 'is_system' => true],
        )->id;
    }

    private function branchId(): int
    {
        return Branch::firstOrCreate(
            ['code' => 'HO'],
            ['name' => 'Head Office', 'city' => 'Colombo'],
        )->id;
    }
}
