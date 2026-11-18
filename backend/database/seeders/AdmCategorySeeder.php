<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdmCategory;
use App\Models\AdmRequestType;
use App\Models\AdmSlaSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdmCategorySeeder extends Seeder
{
    /**
     * @return list<array{slug: string, name: string, sla: int, types: list<string>}>
     */
    public static function catalogue(): array
    {
        return [
            [
                'slug' => 'insurance-management',
                'name' => 'Insurance Management',
                'sla' => 72,
                'types' => [
                    'Insurance Claims',
                    'Policy Expiry Inquiries',
                    'Policy Renewals',
                    'New Policy Requests',
                    'Policy Amendments',
                    'Insurance Document Requests',
                    'Claim Status Inquiries',
                    'Insurance Payment Issues',
                    'Other Insurance Requests',
                ],
            ],
            [
                'slug' => 'fleet-vehicle-management',
                'name' => 'Fleet & Vehicle Management',
                'sla' => 24,
                'types' => [
                    'Vehicle Booking',
                    'Booking Cancellation',
                    'Booking Reschedule',
                    'Vehicle Issue',
                ],
            ],
            [
                'slug' => 'fuel-card-management',
                'name' => 'Fuel Card Management',
                'sla' => 48,
                'types' => [
                    'New Fuel Card',
                    'Fuel Card Allocation',
                    'Lost Fuel Card',
                    'Damaged Fuel Card',
                    'Fuel Transaction Inquiry',
                    'Fuel Card Balance Inquiry',
                    'Fuel Card Deactivation',
                    'Other Fuel Card Requests',
                ],
            ],
            [
                'slug' => 'cctv-surveillance',
                'name' => 'CCTV & Surveillance',
                'sla' => 48,
                'types' => [
                    'CCTV Footage Request',
                    'Camera Not Working',
                    'Recording Issue',
                    'Camera Installation',
                    'Camera Relocation',
                    'Monitoring Request',
                    'DVR / NVR Issue',
                    'Other CCTV Requests',
                ],
            ],
            [
                'slug' => 'security-management',
                'name' => 'Security Management',
                'sla' => 24,
                'types' => [
                    'Security Incident',
                    'Guard Allocation',
                    'Staff Complaint',
                    'Access Control',
                    'Event Security',
                    'Other Security Requests',
                ],
            ],
            [
                'slug' => 'courier-document-dispatch',
                'name' => 'Courier & Document Dispatch',
                'sla' => 24,
                'types' => [
                    'Courier Booking',
                    'Document Dispatch',
                    'Courier Collection',
                    'Courier Tracking',
                    'Delayed / Missing Courier',
                    'Courier Charges Inquiry',
                ],
            ],
            [
                'slug' => 'pickme-transport-booking',
                'name' => 'PickMe & Transport Booking',
                'sla' => 8,
                'types' => [
                    'PickMe Booking',
                    'Booking Cancellation',
                    'Transport Issue',
                    'Fare Dispute',
                    'Corporate Account Request',
                    'Transport Arrangement',
                ],
            ],
            [
                'slug' => 'building-maintenance',
                'name' => 'Building & Maintenance',
                'sla' => 48,
                'types' => [
                    'Electrical Issue',
                    'Plumbing Issue',
                    'Air Conditioning Issue',
                    'Office Repairs',
                    'Furniture Repair',
                    'Cleaning Request',
                ],
            ],
            [
                'slug' => 'office-supplies-equipment',
                'name' => 'Office Supplies & Equipment',
                'sla' => 48,
                'types' => [
                    'Stationery Request',
                    'Printing Request',
                    'Equipment Request',
                    'Consumables Request',
                    'Furniture Request',
                ],
            ],
            [
                'slug' => 'office-facilities-events',
                'name' => 'Office Facilities & Events',
                'sla' => 48,
                'types' => [
                    'Meeting Room Booking',
                    'Office Space Request',
                    'Company Event Support',
                    'Refreshments Request',
                    'Visitor Arrangement',
                    'Event Security',
                ],
            ],
            [
                'slug' => 'general-administration',
                'name' => 'General Administration',
                'sla' => 72,
                'types' => [
                    'General Request',
                    'Document Coordination',
                    'Other Inquiries',
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::catalogue() as $index => $item) {
            $category = AdmCategory::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'default_sla_hours' => $item['sla'],
                ],
            );

            AdmSlaSetting::updateOrCreate(
                [
                    'adm_category_id' => $category->id,
                    'adm_request_type_id' => null,
                ],
                [
                    'target_hours' => $item['sla'],
                    'escalate_to_manager' => true,
                    'is_active' => true,
                ],
            );

            foreach ($item['types'] as $typeIndex => $typeName) {
                $type = AdmRequestType::updateOrCreate(
                    [
                        'adm_category_id' => $category->id,
                        'slug' => Str::slug($typeName),
                    ],
                    [
                        'name' => $typeName,
                        'is_active' => true,
                        'sort_order' => $typeIndex + 1,
                        'sla_hours' => $item['sla'],
                    ],
                );

                AdmSlaSetting::updateOrCreate(
                    [
                        'adm_category_id' => $category->id,
                        'adm_request_type_id' => $type->id,
                    ],
                    [
                        'target_hours' => $item['sla'],
                        'escalate_to_manager' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
