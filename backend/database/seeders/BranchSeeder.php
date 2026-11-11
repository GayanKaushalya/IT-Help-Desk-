<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            // ITPL — Vehicle Sales
            [
                'code' => 'ITPL-ANU',
                'name' => 'ITPL Anuradhapura',
                'city' => 'Anuradhapura',
                'address' => '519/G 30, Jayanthi Mawatha, Anuradhapura',
                'phone' => '+94 25 2053500',
                'sort_order' => 10,
            ],
            [
                'code' => 'ITPL-BYD',
                'name' => 'ITPL BYD',
                'city' => null,
                'address' => null,
                'phone' => null,
                'sort_order' => 11,
            ],
            [
                'code' => 'ITPL-CMB',
                'name' => 'ITPL Colombo',
                'city' => 'Colombo',
                'address' => '131 W A D Ramanayake Mawatha, Colombo',
                'phone' => '+94 11 2341333',
                'sort_order' => 12,
            ],
            [
                'code' => 'ITPL-FT',
                'name' => 'ITPL Fasttrack',
                'city' => null,
                'address' => null,
                'phone' => null,
                'sort_order' => 13,
            ],
            [
                'code' => 'ITPL-FOT-KAN',
                'name' => 'ITPL Foton Kandy',
                'city' => 'Kandy',
                'address' => null,
                'phone' => '07043074256',
                'sort_order' => 14,
            ],
            [
                'code' => 'ITPL-FOT-KEL',
                'name' => 'ITPL Foton Kelaniya',
                'city' => 'Kelaniya',
                'address' => null,
                'phone' => '07767626122',
                'sort_order' => 15,
            ],
            [
                'code' => 'ITPL-KAN',
                'name' => 'ITPL Kandy',
                'city' => 'Kandy',
                'address' => '175, Katugastota Road, Kandy',
                'phone' => '+94 81 2234346',
                'sort_order' => 16,
            ],
            [
                'code' => 'ITPL-KEL',
                'name' => 'ITPL Kelaniya',
                'city' => 'Kelaniya',
                'address' => '573, Kandy Road, Kelaniya',
                'phone' => '+94 11 2910310',
                'sort_order' => 17,
            ],
            [
                'code' => 'ITPL-MAT',
                'name' => 'ITPL Matara',
                'city' => 'Matara',
                'address' => 'No. 153, Madiha Galle Road, Matara',
                'phone' => '+94 41 2233785',
                'sort_order' => 18,
            ],
            [
                'code' => 'ITPL-NEG',
                'name' => 'ITPL Negombo',
                'city' => 'Negombo',
                'address' => '623, Colombo Road, Kurana, Negombo',
                'phone' => '+94 31 2237626',
                'sort_order' => 19,
            ],

            // IMS — Spare Parts
            [
                'code' => 'IMS-EMB',
                'name' => 'IMS Embilipitiya',
                'city' => 'Embilipitiya',
                'address' => 'No. 163, Moraketiya Road, Pallegama, Embilipitiya',
                'phone' => '04772301055',
                'sort_order' => 30,
            ],
            [
                'code' => 'IMS-GAM',
                'name' => 'IMS Gampola',
                'city' => 'Gampola',
                'address' => 'No. 30/13, Gangasiri Road, Gampola',
                'phone' => '08120664677',
                'sort_order' => 31,
            ],
            [
                'code' => 'IMS-HYD',
                'name' => 'IMS Hyde Park',
                'city' => 'Colombo',
                'address' => 'No. 76, Hyde Park Corner, Colombo 02',
                'phone' => '01126499699',
                'sort_order' => 32,
            ],
            [
                'code' => 'IMS-KAN-201',
                'name' => 'IMS Kandy 201',
                'city' => 'Kandy',
                'address' => 'No. 201, Katugastota Road, Kandy',
                'phone' => '08120520766',
                'sort_order' => 33,
            ],
            [
                'code' => 'IMS-KAN-140',
                'name' => 'IMS Kandy 140',
                'city' => 'Kandy',
                'address' => 'No. 140, D S Senanayake Street, Kandy',
                'phone' => '08122343001',
                'sort_order' => 34,
            ],
            [
                'code' => 'IMS-KAN-43',
                'name' => 'IMS Kandy 43',
                'city' => 'Kandy',
                'address' => 'No. 43, D S Senanayake Street, Kandy',
                'phone' => '08122342177',
                'sort_order' => 35,
            ],
            [
                'code' => 'IMS-MAT',
                'name' => 'IMS Matara',
                'city' => 'Matara',
                'address' => 'Lakeview, Kamburugamuwa, Matara',
                'phone' => '04121216000',
                'sort_order' => 36,
            ],

            // ISP — Service Park
            [
                'code' => 'ISP-ANU',
                'name' => 'ISP Anuradhapura',
                'city' => 'Anuradhapura',
                'address' => 'No. 519/G 30, Jayanthi Mawatha, Anuradhapura',
                'phone' => '02520535000',
                'sort_order' => 50,
            ],
            [
                'code' => 'ISP-KAN',
                'name' => 'ISP Kandy',
                'city' => 'Kandy',
                'address' => 'No. 273/D, Katugastota Road, Kandy',
                'phone' => '0812213941',
                'sort_order' => 51,
            ],
            [
                'code' => 'ISP-KEL',
                'name' => 'ISP Kelaniya',
                'city' => 'Kelaniya',
                'address' => 'No. 500, Kandy Road, Kelaniya',
                'phone' => '0112916705',
                'sort_order' => 52,
            ],
            [
                'code' => 'ISP-MAT',
                'name' => 'ISP Matara',
                'city' => 'Matara',
                'address' => 'Lake View, Kamburugamuwa, Galle Road, Matara',
                'phone' => '0412238812',
                'sort_order' => 53,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                $branch + ['is_active' => $branch['is_active'] ?? true],
            );
        }

        $this->removePlaceholderBranches();
    }

    private function removePlaceholderBranches(): void
    {
        $colombo = Branch::query()->where('code', 'ITPL-CMB')->first();
        $kandy = Branch::query()->where('code', 'ITPL-KAN')->first() ?? $colombo;

        $placeholders = Branch::withTrashed()
            ->whereIn('code', ['HO', 'BR-KAN', 'BR-GAL', 'BR-JAF', 'WH', 'BR-NEG'])
            ->get();

        foreach ($placeholders as $branch) {
            $target = $branch->code === 'BR-KAN' ? $kandy : $colombo;

            if ($target) {
                User::withTrashed()
                    ->where('branch_id', $branch->id)
                    ->update(['branch_id' => $target->id]);

                Ticket::withTrashed()
                    ->where('branch_id', $branch->id)
                    ->update(['branch_id' => $target->id]);
            }

            $branch->forceDelete();
        }
    }
}
