<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TicketCategory;
use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = TicketPriority::pluck('id', 'slug');

        $categories = [
            [
                'slug' => 'hardware',
                'name' => 'Hardware',
                'description' => 'Desktops, laptops, printers and peripherals.',
                'default_priority' => 'medium',
                'requires_item_details' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'software',
                'name' => 'Software',
                'description' => 'Operating systems, licensed applications and updates.',
                'default_priority' => 'medium',
                'requires_item_details' => false,
                'sort_order' => 2,
            ],
            [
                'slug' => 'network',
                'name' => 'Network',
                'description' => 'Internet, LAN, Wi-Fi and VPN access issues.',
                'default_priority' => 'high',
                'requires_item_details' => false,
                'sort_order' => 3,
            ],
            [
                'slug' => 'email',
                'name' => 'Email',
                'description' => 'Mailboxes, distribution lists and collaboration tools.',
                'default_priority' => 'medium',
                'requires_item_details' => false,
                'sort_order' => 4,
            ],
            [
                'slug' => 'printer',
                'name' => 'Printer',
                'description' => 'Printers, scanners and related consumables.',
                'default_priority' => 'medium',
                'requires_item_details' => true,
                'sort_order' => 5,
            ],
            [
                'slug' => 'access-request',
                'name' => 'Access / Account',
                'description' => 'New accounts, permissions and password resets.',
                'default_priority' => 'high',
                'requires_item_details' => false,
                'sort_order' => 6,
            ],
            [
                'slug' => 'system-issue',
                'name' => 'System Issue',
                'description' => 'Application errors, outages and unexpected system behaviour.',
                'default_priority' => 'high',
                'requires_item_details' => false,
                'sort_order' => 7,
            ],
            [
                'slug' => 'vehicle-system',
                'name' => 'Vehicle/System Application',
                'description' => 'Vehicle systems and related operational applications.',
                'default_priority' => 'high',
                'requires_item_details' => false,
                'sort_order' => 8,
            ],
            [
                'slug' => 'business-systems',
                'name' => 'Business Systems',
                'description' => 'ERP, CRM and other internal line-of-business systems.',
                'default_priority' => 'high',
                'requires_item_details' => false,
                'sort_order' => 9,
            ],
            [
                'slug' => 'security',
                'name' => 'Security',
                'description' => 'Suspicious activity, malware and data protection concerns.',
                'default_priority' => 'critical',
                'requires_item_details' => false,
                'sort_order' => 10,
            ],
            [
                'slug' => 'other',
                'name' => 'Other',
                'description' => 'Anything that does not fit the categories above.',
                'default_priority' => 'low',
                'requires_item_details' => false,
                'sort_order' => 11,
            ],
        ];

        foreach ($categories as $category) {
            $defaultPriority = $category['default_priority'];
            unset($category['default_priority']);

            TicketCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'default_priority_id' => $priorities[$defaultPriority] ?? null],
            );
        }
    }
}
