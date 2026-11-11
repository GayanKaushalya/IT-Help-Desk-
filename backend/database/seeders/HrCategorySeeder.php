<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HrCategory;
use App\Models\HrRequestType;
use App\Models\HrSlaSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HrCategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            [
                'slug' => 'hr-documents',
                'name' => 'HR Documents',
                'sla' => 48,
                'types' => [
                    'Service Letters',
                    'Employment Confirmation Letters',
                    'Salary Confirmation Letters',
                ],
            ],
            [
                'slug' => 'payroll-salary',
                'name' => 'Payroll & Salary',
                'sla' => 72,
                'types' => [
                    'Salary Issues',
                    'Salary Deductions',
                    'OT Payments',
                    'Payslip Issues',
                ],
                'referrals' => [
                    'salary-issues' => 'finance',
                ],
            ],
            [
                'slug' => 'hrmates-system',
                'name' => 'HRMates System',
                'sla' => 48,
                'types' => [
                    'Login Issues',
                    'Attendance Errors',
                    'Leave Balance Issues',
                    'System Errors',
                ],
                'referrals' => [
                    'system-errors' => 'it',
                    'attendance-errors' => 'it',
                ],
            ],
            [
                'slug' => 'attendance-leave',
                'name' => 'Attendance & Leave',
                'sla' => 48,
                'types' => [
                    'Attendance Corrections',
                    'Leave Requests',
                    'Leave Balance Inquiries',
                ],
            ],
            [
                'slug' => 'employee-information',
                'name' => 'Employee Information',
                'sla' => 72,
                'types' => [
                    'Personal Details Updates',
                    'Bank Account Changes',
                ],
            ],
            [
                'slug' => 'epf-etf',
                'name' => 'EPF / ETF',
                'sla' => 96,
                'types' => [
                    'EPF Inquiries',
                    'ETF Issues',
                    'Contribution Details',
                ],
                'referrals' => [
                    'epf-inquiries' => 'finance',
                    'etf-issues' => 'finance',
                    'contribution-details' => 'finance',
                ],
            ],
            [
                'slug' => 'employee-complaints',
                'name' => 'Employee Complaints',
                'sla' => 120,
                'confidential' => true,
                'types' => [
                    'Workplace Complaints',
                    'Management-Related Complaints',
                    'Employee Grievances',
                ],
            ],
            [
                'slug' => 'other-hr-inquiries',
                'name' => 'Other HR Inquiries',
                'sla' => 72,
                'types' => [
                    'General HR-Related Questions',
                ],
            ],
        ];

        foreach ($catalogue as $index => $item) {
            $category = HrCategory::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'is_confidential' => (bool) ($item['confidential'] ?? false),
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'default_sla_hours' => $item['sla'],
                ],
            );

            HrSlaSetting::updateOrCreate(
                [
                    'hr_category_id' => $category->id,
                    'hr_request_type_id' => null,
                ],
                [
                    'target_hours' => $item['sla'],
                    'escalate_to_manager' => true,
                    'is_active' => true,
                ],
            );

            foreach ($item['types'] as $typeIndex => $typeName) {
                $typeSlug = Str::slug($typeName);
                $type = HrRequestType::updateOrCreate(
                    [
                        'hr_category_id' => $category->id,
                        'slug' => $typeSlug,
                    ],
                    [
                        'name' => $typeName,
                        'is_active' => true,
                        'sort_order' => $typeIndex + 1,
                        'sla_hours' => $item['sla'],
                        'default_referral_department' => $item['referrals'][$typeSlug] ?? null,
                    ],
                );

                HrSlaSetting::updateOrCreate(
                    [
                        'hr_category_id' => $category->id,
                        'hr_request_type_id' => $type->id,
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
