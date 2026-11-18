<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Administration;

use App\Enums\RoleSlug;
use App\Models\AdmCategory;
use App\Models\AdmRequest;
use App\Models\AdmRequestStatus;
use App\Models\AdmRequestType;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\AdmCategorySeeder;
use Database\Seeders\AdmRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $officer;

    private User $manager;

    private User $insuranceOfficer;

    private User $fleetOfficer;

    private AdmCategory $category;

    private AdmRequestType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdmRequestStatusSeeder::class,
            AdmCategorySeeder::class,
        ]);

        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->officer = User::factory()->role(RoleSlug::AdministrationOfficer)->create();
        $this->manager = User::factory()->role(RoleSlug::AdministrationManager)->create();
        $this->insuranceOfficer = User::factory()->role(RoleSlug::InsuranceOfficer)->create();
        $this->fleetOfficer = User::factory()->role(RoleSlug::FleetOfficer)->create();

        $this->category = AdmCategory::query()->where('slug', 'insurance-management')->firstOrFail();
        $this->type = AdmRequestType::query()->where('adm_category_id', $this->category->id)->firstOrFail();
    }

    public function test_seeds_all_categories_and_statuses(): void
    {
        $this->assertSame(11, AdmCategory::query()->count());
        $this->assertSame(10, AdmRequestStatus::query()->count());
        $this->assertSame('submitted', AdmRequestStatus::query()->where('is_default', true)->value('slug'));
        $this->assertEqualsCanonicalizing(
            ['closed', 'rejected', 'cancelled'],
            AdmRequestStatus::query()->where('is_terminal', true)->pluck('slug')->all(),
        );
    }

    public function test_employee_can_load_lookups(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/administration/lookups')
            ->assertOk()
            ->assertJsonCount(11, 'data.categories')
            ->assertJsonCount(10, 'data.statuses');
    }

    public function test_creating_a_request_notifies_staff_and_routes_to_specialists(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/administration/requests', [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'subject' => 'Vehicle policy renewal',
            'description' => 'Please renew the insurance policy for the delivery van.',
            'company_name' => 'Acme Insurance',
            'required_date' => now()->addWeek()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.subject', 'Vehicle policy renewal')
            ->assertJsonPath('data.company_name', 'Acme Insurance')
            ->assertJsonPath('data.status.slug', 'assigned');

        $this->assertMatchesRegularExpression('/^ADM-\d{4}-\d{6}$/', $response->json('data.reference'));
        $this->assertTrue($response->json('data.assigned_to_team'));
        $this->assertSame($this->manager->id, $response->json('data.assignee.id'));

        $id = $response->json('data.id');

        foreach ([$this->officer, $this->manager, $this->insuranceOfficer] as $member) {
            $this->assertDatabaseHas('adm_request_assignments', [
                'adm_request_id' => $id,
                'assigned_to' => $member->id,
                'released_at' => null,
            ]);

            $this->assertTrue(
                UserNotification::query()
                    ->where('user_id', $member->id)
                    ->where('type', 'adm.request.new')
                    ->exists(),
                'Expected staff '.$member->id.' to be notified.',
            );
        }

        // Fleet specialists do not own insurance requests.
        $this->assertDatabaseMissing('adm_request_assignments', [
            'adm_request_id' => $id,
            'assigned_to' => $this->fleetOfficer->id,
        ]);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $this->employee->id)
                ->where('type', 'adm.request.created')
                ->exists(),
        );
    }

    public function test_staff_can_list_requests_and_employees_cannot(): void
    {
        $request = $this->createRequest();

        Sanctum::actingAs($this->officer);

        $this->getJson('/api/v1/administration/staff/requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->id);

        $this->getJson('/api/v1/administration/staff/dashboard')
            ->assertOk()
            ->assertJsonPath('data.statistics.total', 1);

        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/administration/staff/requests')->assertForbidden();
        $this->getJson('/api/v1/administration/requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->id);
    }

    public function test_employee_only_sees_their_own_requests(): void
    {
        $request = $this->createRequest();
        $other = User::factory()->role(RoleSlug::Employee)->create();

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/administration/requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/administration/requests/'.$request->id)->assertNotFound();
    }

    public function test_it_and_hr_only_staff_cannot_reach_the_administration_staff_area(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::HrOfficer)->create());
        $this->getJson('/api/v1/administration/staff/requests')->assertForbidden();

        Sanctum::actingAs(User::factory()->role(RoleSlug::Technician)->create());
        $this->getJson('/api/v1/administration/staff/requests')->assertForbidden();

        Sanctum::actingAs(User::factory()->role(RoleSlug::Administrator)->create());
        $this->getJson('/api/v1/administration/staff/requests')->assertOk();
    }

    public function test_staff_assign_comment_resolve_and_employee_confirms(): void
    {
        $request = $this->createRequest();

        Sanctum::actingAs($this->officer);

        $this->postJson('/api/v1/administration/staff/requests/'.$request->id.'/assign', [
            'assigned_to' => $this->officer->id,
            'note' => 'I will handle this.',
        ])->assertOk()
            ->assertJsonPath('data.assignee.id', $this->officer->id)
            ->assertJsonPath('data.assigned_to_team', false);

        $this->postJson('/api/v1/administration/staff/requests/'.$request->id.'/comments', [
            'body' => 'Internal: chasing the broker.',
            'is_internal' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/administration/staff/requests/'.$request->id.'/comments', [
            'body' => 'We have contacted the insurer.',
        ])->assertCreated();

        $inProgress = AdmRequestStatus::query()->where('slug', 'in-progress')->value('id');

        $this->patchJson('/api/v1/administration/staff/requests/'.$request->id.'/status', [
            'status_id' => $inProgress,
        ])->assertOk()->assertJsonPath('data.status.slug', 'in-progress');

        $this->postJson('/api/v1/administration/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Policy renewed and certificate emailed.',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'resolved')
            ->assertJsonPath('data.awaiting_verification', true);

        Sanctum::actingAs($this->employee);

        // Internal notes stay hidden from the employee.
        $detail = $this->getJson('/api/v1/administration/requests/'.$request->id)->assertOk();
        $bodies = collect($detail->json('data.comments'))->pluck('body')->all();
        $this->assertContains('We have contacted the insurer.', $bodies);
        $this->assertNotContains('Internal: chasing the broker.', $bodies);

        $this->postJson('/api/v1/administration/requests/'.$request->id.'/verify', [
            'decision' => 'confirmed',
        ])->assertOk()->assertJsonPath('data.status.slug', 'closed');

        $this->assertDatabaseHas('adm_request_status_histories', [
            'adm_request_id' => $request->id,
            'changed_by' => $this->employee->id,
        ]);
    }

    public function test_employee_cannot_use_staff_actions_on_their_own_request(): void
    {
        $request = $this->createRequest();

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/administration/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Trying to resolve my own request.',
        ])->assertForbidden();
    }

    public function test_create_validates_subject_and_category_type_pair(): void
    {
        Sanctum::actingAs($this->employee);

        $otherType = AdmRequestType::query()->where('adm_category_id', '!=', $this->category->id)->firstOrFail();

        $this->postJson('/api/v1/administration/requests', [
            'category_id' => $this->category->id,
            'request_type_id' => $otherType->id,
            'description' => 'Short but valid description here.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'request_type_id']);
    }

    private function createRequest(): AdmRequest
    {
        Sanctum::actingAs($this->employee);

        $id = $this->postJson('/api/v1/administration/requests', [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'subject' => 'Policy expiry question',
            'description' => 'When does the fleet policy expire next year?',
        ])->assertCreated()->json('data.id');

        return AdmRequest::query()->findOrFail($id);
    }
}
