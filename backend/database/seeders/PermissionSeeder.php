<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * The permission catalogue. Adding an ability here makes it available to the
 * role matrix; granting it to a role happens in RoleSeeder.
 */
class PermissionSeeder extends Seeder
{
    /**
     * @return array<string, array<string, string>> keyed by group, then slug => name
     */
    public static function catalogue(): array
    {
        return [
            'tickets' => [
                'tickets.view_own' => 'View own tickets',
                'tickets.view_any' => 'View all tickets',
                'tickets.create' => 'Create tickets',
                'tickets.comment' => 'Reply to tickets',
                'tickets.comment_internal' => 'Add internal notes',
                'tickets.assign' => 'Assign tickets',
                'tickets.update_status' => 'Change ticket status',
                'tickets.resolve' => 'Resolve tickets',
                'tickets.issue_verification' => 'Issue verification codes',
                'tickets.close' => 'Close tickets',
                'tickets.delete' => 'Delete tickets',
                'tickets.export' => 'Export tickets',
            ],
            'users' => [
                'users.view' => 'View users',
                'users.create' => 'Create users',
                'users.update' => 'Update users',
                'users.approve' => 'Approve registrations',
                'users.deactivate' => 'Deactivate users',
                'users.reset_password' => 'Reset passwords',
            ],
            'configuration' => [
                'branches.manage' => 'Manage branches',
                'departments.manage' => 'Manage departments',
                'categories.manage' => 'Manage ticket categories',
                'priorities.manage' => 'Manage ticket priorities',
                'statuses.manage' => 'Manage ticket statuses',
                'roles.manage' => 'Manage roles and permissions',
                'settings.manage' => 'Manage system settings',
                'knowledge.manage' => 'Manage knowledge base articles',
            ],
            'insights' => [
                'reports.view' => 'View reports',
                'reports.export' => 'Export reports',
                'audit.view' => 'View audit logs',
            ],
            'hr' => [
                'hr.requests.view_own' => 'View own HR requests',
                'hr.requests.create' => 'Submit HR requests',
                'hr.requests.comment' => 'Comment on HR requests',
                'hr.requests.view_any' => 'View HR requests',
                'hr.requests.view_confidential' => 'View confidential HR complaints',
                'hr.requests.assign' => 'Assign HR requests',
                'hr.requests.update_status' => 'Update HR request status',
                'hr.requests.comment_internal' => 'Add internal HR notes',
                'hr.requests.refer' => 'Refer HR requests to other departments',
                'hr.requests.resolve' => 'Resolve and close HR requests',
                'hr.referrals.manage' => 'Manage HR department referrals',
                'hr.referrals.finance' => 'Handle Finance HR referrals',
                'hr.referrals.it' => 'Handle IT HR referrals',
                'hr.categories.manage' => 'Manage HR categories and request types',
                'hr.sla.manage' => 'Manage HR SLA settings',
                'hr.reports.view' => 'View HR reports',
                'hr.reports.export' => 'Export HR reports',
                'hr.settings.manage' => 'Manage HR settings',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::catalogue() as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'group' => $group],
                );
            }
        }
    }
}
