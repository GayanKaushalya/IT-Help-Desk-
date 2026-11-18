<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdmRequestStatus;
use Illuminate\Database\Seeder;

class AdmRequestStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['slug' => 'submitted', 'name' => 'Submitted', 'color_token' => 'info', 'is_default' => true, 'is_open' => true, 'sort_order' => 1],
            ['slug' => 'under-review', 'name' => 'Under Review', 'color_token' => 'primary', 'is_open' => true, 'sort_order' => 2],
            ['slug' => 'assigned', 'name' => 'Assigned', 'color_token' => 'primary', 'is_open' => true, 'sort_order' => 3],
            ['slug' => 'in-progress', 'name' => 'In Progress', 'color_token' => 'warning', 'is_open' => true, 'sort_order' => 4],
            ['slug' => 'awaiting-employee-information', 'name' => 'Awaiting Employee Information', 'color_token' => 'warning', 'is_open' => true, 'sort_order' => 5],
            ['slug' => 'awaiting-external-service-provider', 'name' => 'Awaiting External Service Provider', 'color_token' => 'warning', 'is_open' => true, 'sort_order' => 6],
            ['slug' => 'resolved', 'name' => 'Resolved', 'color_token' => 'success', 'is_open' => false, 'is_terminal' => false, 'sort_order' => 7],
            ['slug' => 'closed', 'name' => 'Closed', 'color_token' => 'neutral', 'is_open' => false, 'is_terminal' => true, 'sort_order' => 8],
            ['slug' => 'rejected', 'name' => 'Rejected', 'color_token' => 'danger', 'is_open' => false, 'is_terminal' => true, 'sort_order' => 9],
            ['slug' => 'cancelled', 'name' => 'Cancelled', 'color_token' => 'neutral', 'is_open' => false, 'is_terminal' => true, 'sort_order' => 10],
        ];

        foreach ($statuses as $status) {
            AdmRequestStatus::updateOrCreate(
                ['slug' => $status['slug']],
                array_merge([
                    'is_default' => false,
                    'is_terminal' => false,
                    'is_active' => true,
                ], $status),
            );
        }
    }
}
