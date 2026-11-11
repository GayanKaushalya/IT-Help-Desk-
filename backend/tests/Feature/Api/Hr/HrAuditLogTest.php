<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Hr;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_hr_staff_only_see_hr_audit_events(): void
    {
        $officer = User::factory()->role(RoleSlug::HrOfficer)->create();

        AuditLog::query()->create([
            'user_id' => $officer->id,
            'event' => 'hr_request.created',
            'description' => 'HR request raised',
        ]);
        AuditLog::query()->create([
            'user_id' => $officer->id,
            'event' => 'ticket.created',
            'description' => 'IT ticket raised',
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/v1/hr/staff/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'hr_request.created')
            ->assertJsonCount(1, 'data');
    }

    public function test_employees_cannot_view_hr_audit_logs(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Employee)->create());

        $this->getJson('/api/v1/hr/staff/audit-logs')->assertForbidden();
    }
}
