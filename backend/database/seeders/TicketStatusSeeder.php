<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

/**
 * The default ticket workflow:
 *
 *   New -> Assigned -> In Progress -> [On Hold | Awaiting User]
 *       -> Resolved -> (verification code) -> Closed
 *
 * New and Assigned can also jump to Resolved so a technician can finish
 * a ticket and issue the verification code without extra status clicks.
 *
 * Cancelled is a terminal branch for tickets raised in error.
 */
class TicketStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'slug' => 'new',
                'name' => 'New',
                'color_token' => 'info',
                'is_default' => true,
                'is_open' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'assigned',
                'name' => 'Assigned',
                'color_token' => 'primary',
                'is_open' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'in-progress',
                'name' => 'In Progress',
                'color_token' => 'primary',
                'is_open' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'on-hold',
                'name' => 'On Hold',
                'color_token' => 'warning',
                'is_open' => true,
                'sort_order' => 4,
            ],
            [
                'slug' => 'awaiting-user',
                'name' => 'Awaiting User',
                'color_token' => 'warning',
                'is_open' => true,
                'sort_order' => 5,
            ],
            [
                'slug' => 'resolved',
                'name' => 'Resolved',
                'color_token' => 'success',
                'is_open' => false,
                'is_resolved' => true,
                'requires_verification' => true,
                'sort_order' => 6,
            ],
            [
                'slug' => 'closed',
                'name' => 'Closed',
                'color_token' => 'neutral',
                'is_open' => false,
                'is_resolved' => true,
                'is_closed' => true,
                'sort_order' => 7,
            ],
            [
                'slug' => 'cancelled',
                'name' => 'Cancelled',
                'color_token' => 'danger',
                'is_open' => false,
                'is_closed' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($statuses as $status) {
            TicketStatus::updateOrCreate(['slug' => $status['slug']], $status);
        }
    }
}
