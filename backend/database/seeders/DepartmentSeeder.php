<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'IT', 'name' => 'Information Technology', 'sort_order' => 1],
            ['code' => 'FIN', 'name' => 'Finance', 'sort_order' => 2],
            ['code' => 'HR', 'name' => 'Human Resources', 'sort_order' => 3],
            ['code' => 'OPS', 'name' => 'Operations', 'sort_order' => 4],
            ['code' => 'SLS', 'name' => 'Sales and Marketing', 'sort_order' => 5],
            ['code' => 'SVC', 'name' => 'After Sales Service', 'sort_order' => 6],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['code' => $department['code']], $department);
        }
    }
}
