<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Hr;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Seeders\HrCategorySeeder;
use Database\Seeders\HrRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            HrRequestStatusSeeder::class,
            HrCategorySeeder::class,
        ]);
    }

    public function test_an_hr_officer_can_view_hr_reports(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::HrOfficer)->create());

        $this->getJson('/api/v1/hr/staff/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'generated_at',
                    'filters',
                    'kpis' => [
                        'request_volume',
                        'open_requests',
                        'completed_requests',
                        'overdue_requests',
                        'complaints',
                        'referrals',
                    ],
                    'charts' => [
                        'volume',
                        'by_branch',
                        'by_category',
                        'by_request_type',
                        'by_status',
                        'by_staff',
                        'aging',
                        'referrals',
                    ],
                ],
            ]);
    }

    public function test_an_employee_cannot_view_hr_reports(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Employee)->create());

        $this->getJson('/api/v1/hr/staff/reports')->assertForbidden();
    }

    public function test_an_hr_manager_can_export_hr_reports_as_csv(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::HrManager)->create());

        $response = $this->get('/api/v1/hr/staff/reports/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('HR Helpdesk Reports', $response->streamedContent());
    }

    public function test_an_hr_officer_cannot_export_hr_reports(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::HrOfficer)->create());

        $this->getJson('/api/v1/hr/staff/reports/export?format=csv')
            ->assertForbidden();
    }
}
