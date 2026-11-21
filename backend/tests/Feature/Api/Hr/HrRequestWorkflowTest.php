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
use Database\Seeders\HrCategorySeeder;
use Database\Seeders\HrRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $officer;

    private User $manager;

    private HrCategory $category;

    private HrRequestType $type;

    protected function setUp(): void
    {
        parent::setUp();

        // Factory users have random names; do not restrict the HR desk to named staff.
        config(['hr.assignable_name_keywords' => []]);

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            HrRequestStatusSeeder::class,
            HrCategorySeeder::class,
        ]);

        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->officer = User::factory()->role(RoleSlug::HrOfficer)->create();
        $this->manager = User::factory()->role(RoleSlug::HrManager)->create();

        $this->category = HrCategory::query()->where('is_confidential', false)->firstOrFail();
        $this->type = HrRequestType::query()->where('hr_category_id', $this->category->id)->firstOrFail();
    }

    public function test_new_requests_are_assigned_to_every_hr_staff_member(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/hr/requests', [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'description' => 'I need a letter confirming my employment for the bank.',
            'contact_phone' => '0771234567',
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->assertTrue($response->json('data.assigned_to_team'));
        $this->assertSame($this->manager->id, $response->json('data.assignee.id'));
        $this->assertSame('assigned', $response->json('data.status.slug'));

        $this->assertDatabaseHas('hr_request_assignments', [
            'hr_request_id' => $id,
            'assigned_to' => $this->officer->id,
            'released_at' => null,
        ]);
        $this->assertDatabaseHas('hr_request_assignments', [
            'hr_request_id' => $id,
            'assigned_to' => $this->manager->id,
            'released_at' => null,
        ]);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $this->officer->id)
                ->where('type', 'hr.request.new')
                ->exists(),
        );
    }

    public function test_employee_must_confirm_resolution_before_close(): void
    {
        $request = $this->createAssignedRequest();

        Sanctum::actingAs($this->officer);

        $this->postJson('/api/v1/hr/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Employment letter prepared and emailed.',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'resolved')
            ->assertJsonPath('data.awaiting_verification', true)
            ->assertJsonPath('data.assignee.id', $this->officer->id)
            ->assertJsonPath('data.assigned_to_team', false);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $this->employee->id)
                ->where('type', 'hr.request.verification_required')
                ->exists(),
        );

        $this->patchJson('/api/v1/hr/staff/requests/'.$request->id.'/status', [
            'status_id' => HrRequestStatus::query()->where('slug', 'closed')->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['status_id']);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/hr/requests/'.$request->id.'/verify', [
            'decision' => 'confirmed',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'closed')
            ->assertJsonPath('data.awaiting_verification', false);
    }

    public function test_employee_can_reject_resolution(): void
    {
        $request = $this->createAssignedRequest();

        Sanctum::actingAs($this->officer);
        $this->postJson('/api/v1/hr/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Employment letter prepared and emailed.',
        ])->assertOk();

        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/hr/requests/'.$request->id.'/verify', [
            'decision' => 'rejected',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'in-progress');
    }

    public function test_closing_requires_an_individual_assignee(): void
    {
        Sanctum::actingAs($this->employee);
        $id = $this->postJson('/api/v1/hr/requests', [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'description' => 'Need help updating my emergency contact details today.',
            'contact_phone' => '0771234567',
        ])->assertCreated()->json('data.id');

        $request = HrRequest::query()->findOrFail($id);

        Sanctum::actingAs($this->officer);
        $this->postJson('/api/v1/hr/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Contact details updated in the HR system.',
        ])->assertOk();

        $request->forceFill(['assigned_to' => null])->save();
        $request->assignments()->current()->update(['released_at' => now()]);

        $this->postJson('/api/v1/hr/staff/requests/'.$request->id.'/close-as-verified')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    }

    private function createAssignedRequest(): HrRequest
    {
        Sanctum::actingAs($this->employee);

        $id = $this->postJson('/api/v1/hr/requests', [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'description' => 'I need a letter confirming my employment for the bank.',
            'contact_phone' => '0771234567',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->officer);
        $this->postJson('/api/v1/hr/staff/requests/'.$id.'/assign', [
            'assigned_to' => $this->officer->id,
        ])->assertOk();

        return HrRequest::query()->findOrFail($id);
    }
}
