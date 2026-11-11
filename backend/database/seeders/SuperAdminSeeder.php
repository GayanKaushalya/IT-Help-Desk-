<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the initial super admin so the system is reachable after install.
 *
 * The password comes from the environment; it is never committed and the
 * account should have it changed at first login.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_SUPER_ADMIN_PASSWORD');

        if (blank($password)) {
            $this->command?->warn('SEED_SUPER_ADMIN_PASSWORD is not set - skipping super admin creation.');

            return;
        }

        $role = Role::where('slug', RoleSlug::SuperAdmin->value)->first();
        $branch = Branch::where('code', 'ITPL-CMB')->first()
            ?? Branch::query()->active()->ordered()->first();
        $department = Department::where('code', 'IT')->first();

        if (! $role || ! $branch) {
            $this->command?->warn('Reference data is missing - run RoleSeeder and BranchSeeder first.');

            return;
        }

        $username = (string) env('SEED_SUPER_ADMIN_USERNAME', 'superadmin');

        $user = User::withTrashed()->firstOrNew(['username' => $username]);

        $user->fill([
            'full_name' => (string) env('SEED_SUPER_ADMIN_NAME', 'System Administrator'),
            'employee_id' => (string) env('SEED_SUPER_ADMIN_EMPLOYEE_ID', 'EMP-0001'),
            'epf_number' => (string) env('SEED_SUPER_ADMIN_EPF', 'EPF-0001'),
            'email' => env('SEED_SUPER_ADMIN_EMAIL'),
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'department_id' => $department?->id,
            'job_title' => 'IT Administrator',
            'is_active' => true,
            'is_approved' => true,
        ]);

        // Only set on creation so re-seeding never resets a changed password.
        if (! $user->exists) {
            $user->password = $password;
            $user->password_changed_at = now();
            $user->approved_at = now();
        }

        $user->save();

        $this->command?->info("Super admin ready: {$username}");
    }
}
