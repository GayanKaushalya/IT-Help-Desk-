<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Hr;

use App\Enums\RoleSlug;
use App\Models\HrCategory;
use App\Models\HrRequest;
use App\Models\HrRequestType;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Hr\HrRequestService;
use Database\Seeders\HrCategorySeeder;
use Database\Seeders\HrRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrVerificationWindowTest extends TestCase
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

    public function test_store_requires_a_valid_contact_phone(): void
    {
        Sanctum::actingAs($this->employee);

        $payload = [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'description' => 'I need a letter confirming my employment for the bank.',
        ];

        $this->postJson('/api/v1/hr/requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_phone']);

        $this->postJson('/api/v1/hr/requests', $payload + ['contact_phone' => 'call-me'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_phone']);

        $this->postJson('/api/v1/hr/requests', $payload + ['contact_phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_phone']);

        $this->postJson('/api/v1/hr/requests', $payload + ['contact_phone' => '+94 77 123 4567'])
            ->assertCreated()
            ->assertJsonPath('data.contact_phone', '+94 77 123 4567');

        $this->assertDatabaseHas('hr_requests', ['contact_phone' => '+94 77 123 4567']);
    }

    public function test_service_defaults_the_contact_phone_from_the_user_profile(): void
    {
        $this->employee->forceFill(['phone' => '0112223344'])->save();

        $request = app(HrRequestService::class)->create($this->employee->fresh(), [
            'category_id' => $this->category->id,
            'request_type_id' => $this->type->id,
            'description' => 'I need a letter confirming my employment for the bank.',
        ]);

        $this->assertSame('0112223344', $request->contact_phone);
    }

    public function test_resolving_starts_a_two_day_verification_window(): void
    {
        $request = $this->createAssignedRequest();

        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/hr/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Employment letter prepared and emailed.',
        ])->assertOk()->assertJsonPath('data.contact_phone', '0771234567');

        $due = $request->fresh()->verification_due_at;

        $this->assertNotNull($due);
        $this->assertEqualsWithDelta(
            now()->addDays(2)->timestamp,
            $due->timestamp,
            120,
        );
        $this->assertNotNull($response->json('data.verification_due_at'));

        $notification = UserNotification::query()
            ->where('user_id', $this->employee->id)
            ->where('type', 'hr.request.verification_required')
            ->firstOrFail();

        $this->assertStringContainsString('within 2 days', (string) $notification->body);
    }

    public function test_employee_rejection_clears_the_verification_window(): void
    {
        $request = $this->resolvedRequest();

        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/hr/requests/'.$request->id.'/verify', ['decision' => 'rejected'])
            ->assertOk();

        $this->assertNull($request->fresh()->verification_due_at);
    }

    public function test_employee_confirmation_notifies_all_desk_staff(): void
    {
        $request = $this->resolvedRequest();

        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/hr/requests/'.$request->id.'/verify', ['decision' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status.slug', 'closed')
            ->assertJsonPath('data.closed_by.id', $this->employee->id);

        $request->refresh();
        $this->assertNull($request->verification_due_at);

        $this->assertDatabaseHas('hr_request_status_histories', [
            'hr_request_id' => $request->id,
            'changed_by' => $this->employee->id,
            'remarks' => 'Verified and closed by '.$this->employee->full_name.'.',
        ]);

        foreach ([$this->officer, $this->manager] as $member) {
            $notification = UserNotification::query()
                ->where('user_id', $member->id)
                ->where('type', 'hr.request.verified')
                ->first();

            $this->assertNotNull($notification, 'Expected desk staff '.$member->id.' to be notified.');
            $this->assertSame('Verified by employee', $notification->title);
            $this->assertSame(
                $this->employee->full_name.' verified and closed '.$request->reference.'.',
                $notification->body,
            );
        }
    }

    public function test_command_closes_requests_after_the_window_lapses(): void
    {
        $expired = $this->resolvedRequest();
        $this->travel(3)->days();
        $fresh = $this->resolvedRequest();

        $this->artisan('helpdesk:close-expired-verifications')->assertSuccessful();

        $expired->refresh();
        $this->assertSame('closed', $expired->status->slug);
        $this->assertSame($this->employee->id, (int) $expired->closed_by);
        $this->assertNotNull($expired->closed_at);
        $this->assertNull($expired->verification_due_at);

        $this->assertDatabaseHas('hr_request_status_histories', [
            'hr_request_id' => $expired->id,
            'changed_by' => $this->employee->id,
            'remarks' => 'Automatically closed after 2 days awaiting employee verification.',
        ]);

        foreach ([$this->officer, $this->manager] as $member) {
            $this->assertTrue(
                UserNotification::query()
                    ->where('user_id', $member->id)
                    ->where('type', 'hr.request.verified')
                    ->get()
                    ->contains(fn (UserNotification $item) => (int) ($item->data['hr_request_id'] ?? 0) === (int) $expired->id),
                'Expected desk staff '.$member->id.' to be told about the automatic close.',
            );
        }

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $this->employee->id)
                ->where('type', 'hr.request.closed')
                ->exists(),
        );

        // Still inside its window: untouched.
        $fresh->refresh();
        $this->assertSame('resolved', $fresh->status->slug);
        $this->assertNotNull($fresh->verification_due_at);
        $this->assertNull($fresh->closed_at);
    }

    private function resolvedRequest(): HrRequest
    {
        $request = $this->createAssignedRequest();

        Sanctum::actingAs($this->officer);
        $this->postJson('/api/v1/hr/staff/requests/'.$request->id.'/resolve', [
            'resolution_summary' => 'Employment letter prepared and emailed.',
        ])->assertOk();

        return $request->fresh();
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
