<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Hr;

use App\Enums\RoleSlug;
use App\Models\HrCategory;
use App\Models\HrRequest;
use App\Models\HrRequestStatus;
use App\Models\HrRequestType;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Hr\HrEscalationService;
use Database\Seeders\HrCategorySeeder;
use Database\Seeders\HrRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_requests_are_escalated_to_the_hr_manager(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            HrRequestStatusSeeder::class,
            HrCategorySeeder::class,
        ]);

        $employee = User::factory()->role(RoleSlug::Employee)->create();
        $manager = User::factory()->role(RoleSlug::HrManager)->create();
        $officer = User::factory()->role(RoleSlug::HrOfficer)->create();

        $category = HrCategory::query()->where('slug', 'hr-documents')->firstOrFail();
        $type = HrRequestType::query()->where('hr_category_id', $category->id)->firstOrFail();
        $status = HrRequestStatus::query()->where('slug', 'in-progress')->firstOrFail();

        $request = HrRequest::query()->create([
            'reference' => 'HR-2026-009999',
            'requester_id' => $employee->id,
            'employee_id' => 'E001',
            'employee_name' => $employee->full_name,
            'hr_category_id' => $category->id,
            'hr_request_type_id' => $type->id,
            'status_id' => $status->id,
            'description' => 'Need a service letter urgently for a visa application.',
            'assigned_to' => $officer->id,
            'assigned_at' => now()->subDays(3),
            'due_at' => now()->subHour(),
        ]);

        $result = app(HrEscalationService::class)->escalateOverdue();

        $this->assertSame(1, $result['escalated']);
        $request->refresh();
        $this->assertNotNull($request->escalated_at);
        $this->assertSame($manager->id, $request->escalated_to);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $manager->id)
                ->where('type', 'hr.request.escalated')
                ->exists(),
        );
    }

    public function test_hr_manager_can_update_sla_targets(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            HrRequestStatusSeeder::class,
            HrCategorySeeder::class,
        ]);

        $manager = User::factory()->role(RoleSlug::HrManager)->create();
        $category = HrCategory::query()->where('slug', 'payroll-salary')->firstOrFail();

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/hr/staff/sla/categories/'.$category->id, [
            'target_hours' => 36,
            'escalate_to_manager' => true,
        ])->assertOk();

        $payload = $this->getJson('/api/v1/hr/staff/sla')->assertOk()->json('data');
        $row = collect($payload)->firstWhere('id', $category->id);

        $this->assertSame(36, $row['target_hours']);
        $this->assertTrue($row['escalate_to_manager']);
    }
}
