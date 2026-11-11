<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Creates the system roles and grants each one its abilities.
 *
 * IT roles stay cumulative. HR roles are separate so IT workflows remain intact.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $employee = [
            'tickets.view_own',
            'tickets.create',
            'tickets.comment',
            'hr.requests.view_own',
            'hr.requests.create',
            'hr.requests.comment',
        ];

        $technician = [
            ...$employee,
            'tickets.view_any',
            'tickets.comment_internal',
            'tickets.update_status',
            'tickets.resolve',
            'tickets.issue_verification',
            'users.view',
            'knowledge.manage',
            'hr.referrals.it',
        ];

        $supervisor = [
            ...$technician,
            'tickets.assign',
            'tickets.close',
            'tickets.export',
            'reports.view',
        ];

        // HR staff also act as employees on the IT desk (raise and track tickets).
        $hrAsEmployeeIt = [
            'tickets.view_own',
            'tickets.create',
            'tickets.comment',
        ];

        $hrOfficer = [
            ...$hrAsEmployeeIt,
            'hr.requests.view_own',
            'hr.requests.create',
            'hr.requests.comment',
            'hr.requests.view_any',
            'hr.requests.assign',
            'hr.requests.update_status',
            'hr.requests.comment_internal',
            'hr.requests.refer',
            'hr.requests.resolve',
            'hr.referrals.manage',
            'hr.reports.view',
        ];

        $hrManager = [
            ...$hrOfficer,
            'hr.requests.view_confidential',
            'hr.categories.manage',
            'hr.sla.manage',
            'hr.reports.export',
            'hr.settings.manage',
            'users.view',
        ];

        // System Administrators (IT) administer both desks.
        $administrator = [
            ...$supervisor,
            ...$hrManager,
            'tickets.delete',
            'users.create',
            'users.update',
            'users.approve',
            'users.deactivate',
            'users.reset_password',
            'branches.manage',
            'departments.manage',
            'categories.manage',
            'priorities.manage',
            'statuses.manage',
            'reports.export',
            'audit.view',
            'settings.manage',
            'hr.referrals.finance',
            'hr.referrals.it',
        ];

        $financeOfficer = [
            ...$hrAsEmployeeIt,
            'hr.referrals.finance',
            'hr.referrals.manage',
        ];

        $confidentialHandler = [
            ...$hrAsEmployeeIt,
            'hr.requests.view_own',
            'hr.requests.comment',
            'hr.requests.view_any',
            'hr.requests.view_confidential',
            'hr.requests.update_status',
            'hr.requests.comment_internal',
            'hr.requests.resolve',
        ];

        // Super admins bypass the matrix in User::hasPermission(), but the rows
        // are still granted so the admin UI reflects reality.
        $superAdmin = [
            ...$administrator,
            'roles.manage',
        ];

        $matrix = [
            RoleSlug::Employee->value => $employee,
            RoleSlug::Technician->value => $technician,
            RoleSlug::Supervisor->value => $supervisor,
            RoleSlug::Administrator->value => $administrator,
            RoleSlug::SuperAdmin->value => $superAdmin,
            RoleSlug::HrOfficer->value => $hrOfficer,
            RoleSlug::HrManager->value => $hrManager,
            RoleSlug::FinanceOfficer->value => $financeOfficer,
            RoleSlug::ConfidentialComplaintHandler->value => $confidentialHandler,
        ];

        foreach ($matrix as $slug => $permissions) {
            $roleSlug = RoleSlug::from($slug);

            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleSlug->label(),
                    'level' => $roleSlug->level(),
                    'is_system' => true,
                    'is_active' => true,
                ],
            );

            $role->permissions()->sync(
                Permission::whereIn('slug', array_unique($permissions))->pluck('id')
            );
        }
    }
}
