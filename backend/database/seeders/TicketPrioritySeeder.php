<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

class TicketPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            [
                'slug' => 'low',
                'name' => 'Low',
                'level' => 10,
                'color_token' => 'neutral',
                'response_minutes' => 480,
                'resolution_minutes' => 4320,
                'sort_order' => 1,
            ],
            [
                'slug' => 'medium',
                'name' => 'Medium',
                'level' => 20,
                'color_token' => 'info',
                'response_minutes' => 240,
                'resolution_minutes' => 1440,
                'is_default' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'high',
                'name' => 'High',
                'level' => 30,
                'color_token' => 'warning',
                'response_minutes' => 60,
                'resolution_minutes' => 480,
                'sort_order' => 3,
            ],
            [
                'slug' => 'critical',
                'name' => 'Critical',
                'level' => 40,
                'color_token' => 'danger',
                'response_minutes' => 15,
                'resolution_minutes' => 240,
                'sort_order' => 4,
            ],
        ];

        foreach ($priorities as $priority) {
            TicketPriority::updateOrCreate(['slug' => $priority['slug']], $priority);
        }
    }
}
