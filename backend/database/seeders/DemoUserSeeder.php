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
 * Local-only accounts used to exercise each role during development.
 *
 * Production deployments should leave SEED_DEMO_USERS unset.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $password = (string) env('SEED_DEMO_PASSWORD', 'ChangeMe@2026');
        $branch = Branch::where('code', 'ITPL-CMB')->first()
            ?? Branch::query()->active()->ordered()->first();
        $department = Department::where('code', 'IT')->first();

        if (! $branch) {
            $this->command?->warn('Demo users skipped: branches have not been seeded.');

            return;
        }

        $accounts = [
            [
                'username' => 'employee',
                'full_name' => 'Nimal Perera',
                'employee_id' => 'EMP-1001',
                'epf_number' => 'EPF-1001',
                'role' => RoleSlug::Employee,
                'job_title' => 'Operations Executive',
            ],
            [
                'username' => 'itsupport',
                'full_name' => 'Kavindi Silva',
                'employee_id' => 'EMP-1002',
                'epf_number' => 'EPF-1002',
                'role' => RoleSlug::Technician,
                'job_title' => 'IT Support Technician',
            ],
            [
                'username' => 'itadmin',
                'full_name' => 'Ruwan Jayasuriya',
                'employee_id' => 'EMP-1003',
                'epf_number' => 'EPF-1003',
                'role' => RoleSlug::Administrator,
                'job_title' => 'IT Administrator',
            ],
        ];

        foreach ($accounts as $account) {
            $role = Role::where('slug', $account['role']->value)->first();

            if (! $role) {
                continue;
            }

            $user = User::withTrashed()->firstOrNew(['username' => $account['username']]);

            $user->fill([
                'full_name' => $account['full_name'],
                'employee_id' => $account['employee_id'],
                'epf_number' => $account['epf_number'],
                'role_id' => $role->id,
                'branch_id' => $branch->id,
                'department_id' => $department?->id,
                'job_title' => $account['job_title'],
                'is_active' => true,
                'is_approved' => true,
            ]);

            if (! $user->exists) {
                $user->password = $password;
                $user->password_changed_at = now();
                $user->approved_at = now();
            }

            $user->save();
        }

        $this->command?->info('Demo users ready: employee, itsupport, itadmin');
    }
}
