<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $technician;

    private User $administrator;

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

        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();
    }

    public function test_an_employee_cannot_browse_audit_logs(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_a_technician_cannot_browse_audit_logs(): void
    {
        Sanctum::actingAs($this->technician);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden()
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_an_administrator_can_filter_append_only_audit_logs(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/auth/logout')->assertOk();
        Sanctum::actingAs($this->administrator);

        $response = $this->getJson('/api/v1/admin/audit-logs?event=auth.logout')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
        $this->assertSame('auth.logout', $response->json('data.0.event'));
        $this->assertSame('Logout', $response->json('data.0.action'));
        $this->assertSame('auth', $response->json('data.0.entity_type'));
        $this->assertArrayHasKey('ip_address', $response->json('data.0'));
        $this->assertArrayHasKey('user_agent', $response->json('data.0'));
        $this->assertArrayNotHasKey('password', $response->json('data.0'));

        $this->getJson('/api/v1/admin/audit-logs?entity_type=auth')
            ->assertOk()
            ->assertJsonPath('data.0.entity_type', 'auth');

        $this->patchJson('/api/v1/admin/audit-logs/1', ['description' => 'tamper'])
            ->assertNotFound();
        $this->deleteJson('/api/v1/admin/audit-logs/1')
            ->assertNotFound();

        $row = AuditLog::query()->firstOrFail();
        $this->assertFalse($row->update(['description' => 'tampered']));
        $this->assertNotSame('tampered', $row->fresh()?->description);
    }

    public function test_audit_lookups_are_available_to_administrators(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->getJson('/api/v1/admin/audit-logs/lookups')
            ->assertOk()
            ->assertJsonPath('data.events.0.value', 'auth.login');
    }
}
