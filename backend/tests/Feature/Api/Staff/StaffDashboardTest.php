<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Staff;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            TicketPrioritySeeder::class,
            TicketStatusSeeder::class,
            TicketCategorySeeder::class,
        ]);
    }

    public function test_an_employee_cannot_open_the_staff_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Employee)->create());

        $this->getJson('/api/v1/staff/dashboard')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_staff_receive_organisation_wide_statistics(): void
    {
        $employee = User::factory()->role(RoleSlug::Employee)->create();
        $technician = User::factory()->role(RoleSlug::Technician)->create();

        Ticket::factory()->forRequester($employee)->count(2)->create();
        Ticket::factory()->forRequester($employee)->assignedTo($technician)->status('in-progress')->create();
        Ticket::factory()->forRequester($employee)->status('resolved')->create();

        Sanctum::actingAs($technician);

        $response = $this->getJson('/api/v1/staff/dashboard')->assertOk();

        $this->assertSame(4, $response->json('data.statistics.total'));
        $this->assertSame(2, $response->json('data.statistics.open'));
        $this->assertSame(1, $response->json('data.statistics.in_progress'));
        $this->assertSame(1, $response->json('data.statistics.resolved'));
        $this->assertNotEmpty($response->json('data.charts.by_status'));
        $this->assertNotEmpty($response->json('data.charts.over_time'));
        $this->assertNotEmpty($response->json('data.charts.workload'));
        $this->assertCount(4, $response->json('data.recent_tickets'));
    }

    public function test_staff_can_list_agent_workload(): void
    {
        $technician = User::factory()->role(RoleSlug::Technician)->create();
        Ticket::factory()->assignedTo($technician)->create();

        Sanctum::actingAs(User::factory()->role(RoleSlug::Administrator)->create());

        $response = $this->getJson('/api/v1/staff/agents')->assertOk();
        $agent = collect($response->json('data'))->firstWhere('id', $technician->id);

        $this->assertNotNull($agent);
        $this->assertSame(1, $agent['open_tickets']);
        $this->assertSame(1, $agent['total_assigned']);
    }
}
