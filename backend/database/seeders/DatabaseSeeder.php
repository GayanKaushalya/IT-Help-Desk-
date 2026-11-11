<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeders are ordered by dependency: permissions before roles, priorities
     * before categories, and everything before the super admin account.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            BranchSeeder::class,
            DepartmentSeeder::class,
            TicketPrioritySeeder::class,
            TicketStatusSeeder::class,
            TicketCategorySeeder::class,
            HrRequestStatusSeeder::class,
            HrCategorySeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
