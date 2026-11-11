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

/**
 * System Administrators administer both the IT and HR service desks.
 */
class AdministratorHrAccessTest extends TestCase
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

    public function test_an_administrator_can_open_the_hr_staff_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Administrator)->create());

        $this->getJson('/api/v1/hr/staff/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'statistics',
                    'by_category',
                    'by_status',
                    'recent_requests',
                ],
            ]);
    }

    public function test_an_administrator_can_view_and_export_hr_reports(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Administrator)->create());

        $this->getJson('/api/v1/hr/staff/reports')
            ->assertOk()
            ->assertJsonPath('data.kpis.request_volume', 0);

        $export = $this->get('/api/v1/hr/staff/reports/export?format=csv');
        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
    }

    public function test_an_administrator_holds_hr_admin_permissions(): void
    {
        $admin = User::factory()->role(RoleSlug::Administrator)->create();

        $this->assertTrue($admin->isHrStaff());
        $this->assertTrue($admin->canViewConfidentialHr());
        $this->assertTrue($admin->hasPermission('hr.requests.view_any'));
        $this->assertTrue($admin->hasPermission('hr.requests.assign'));
        $this->assertTrue($admin->hasPermission('hr.requests.resolve'));
        $this->assertTrue($admin->hasPermission('hr.reports.view'));
        $this->assertTrue($admin->hasPermission('hr.reports.export'));
        $this->assertTrue($admin->hasPermission('hr.settings.manage'));
        $this->assertTrue($admin->hasPermission('tickets.view_any'));
        $this->assertTrue($admin->hasPermission('settings.manage'));
    }
}
