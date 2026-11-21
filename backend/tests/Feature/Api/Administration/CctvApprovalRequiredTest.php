<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Administration;

use App\Enums\RoleSlug;
use App\Models\AdmCategory;
use App\Models\AdmRequest;
use App\Models\AdmRequestType;
use App\Models\CctvApproval;
use App\Models\User;
use Database\Seeders\AdmCategorySeeder;
use Database\Seeders\AdmRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CctvApprovalRequiredTest extends TestCase
{
    use RefreshDatabase;

    private const STAFF = '/api/v1/administration/staff/requests/';

    private User $employee;

    private User $officer;

    private User $approver;

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
        $this->approver = User::factory()->role(RoleSlug::CctvApprover)->create();
    }

    private function submit(string $categorySlug = 'cctv-surveillance'): AdmRequest
    {
        Sanctum::actingAs($this->employee);

        $category = AdmCategory::query()->where('slug', $categorySlug)->firstOrFail();
        $type = AdmRequestType::query()->where('adm_category_id', $category->id)->firstOrFail();

        $id = $this->postJson('/api/v1/administration/requests', [
            'category_id' => $category->id,
            'request_type_id' => $type->id,
            'subject' => 'Footage for lobby incident',
            'description' => 'Need lobby camera footage from 2pm to 3pm yesterday.',
            'contact_phone' => '0771234567',
            'details' => ['location' => 'Main lobby', 'footage_from' => '2030-01-01 14:00', 'footage_to' => '2030-01-01 15:00'],
        ])->assertCreated()->json('data.id');

        return AdmRequest::query()->findOrFail($id);
    }

    public function test_cctv_request_gets_a_pending_approval_and_stores_details(): void
    {
        $request = $this->submit();

        $this->assertDatabaseHas('cctv_approvals', ['adm_request_id' => $request->id, 'status' => 'pending']);
        $this->assertDatabaseHas('cctv_access_logs', ['adm_request_id' => $request->id, 'action' => 'approval_requested']);
        $this->assertSame('Main lobby', $request->details['location']);

        // Other categories never get an approval record.
        $other = $this->submit('general-administration');
        $this->assertDatabaseMissing('cctv_approvals', ['adm_request_id' => $other->id]);
    }

    public function test_cctv_request_cannot_be_resolved_without_approval(): void
    {
        $request = $this->submit();

        Sanctum::actingAs($this->officer);
        $this->postJson(self::STAFF.$request->id.'/assign', ['assigned_to' => $this->officer->id])->assertOk();

        $this->postJson(self::STAFF.$request->id.'/resolve', ['resolution_summary' => 'Footage exported.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cctv_approval']);

        $this->patchJson(self::STAFF.$request->id.'/status', [
            'status_id' => \App\Models\AdmRequestStatus::query()->where('slug', 'resolved')->value('id'),
            'remarks' => 'Done',
        ])->assertUnprocessable()->assertJsonValidationErrors(['cctv_approval']);
    }

    public function test_approver_approves_then_the_desk_can_resolve(): void
    {
        $request = $this->submit();

        Sanctum::actingAs($this->officer);
        $this->postJson(self::STAFF.$request->id.'/assign', ['assigned_to' => $this->officer->id])->assertOk();

        // The desk officer is not an approver.
        $this->postJson(self::STAFF.$request->id.'/cctv/approve')->assertForbidden();

        Sanctum::actingAs($this->approver);
        $this->postJson(self::STAFF.$request->id.'/cctv/approve', ['decision_notes' => 'Valid security reason.'])
            ->assertOk()
            ->assertJsonPath('data.approval.status', 'approved')
            ->assertJsonPath('data.approval.decided_by.id', $this->approver->id);

        $this->assertDatabaseHas('cctv_approvals', [
            'adm_request_id' => $request->id,
            'status' => 'approved',
            'decided_by' => $this->approver->id,
        ]);
        $this->assertDatabaseHas('cctv_access_logs', ['adm_request_id' => $request->id, 'action' => 'approved']);

        Sanctum::actingAs($this->officer);
        $this->postJson(self::STAFF.$request->id.'/resolve', ['resolution_summary' => 'Footage exported.'])
            ->assertOk()
            ->assertJsonPath('data.status.slug', 'resolved');
    }

    public function test_rejection_needs_a_reason_and_keeps_the_request_unresolvable(): void
    {
        $request = $this->submit();

        Sanctum::actingAs($this->approver);

        $this->postJson(self::STAFF.$request->id.'/cctv/reject')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['decision_notes']);

        $this->postJson(self::STAFF.$request->id.'/cctv/reject', ['decision_notes' => 'No justification given.'])
            ->assertOk()
            ->assertJsonPath('data.approval.status', 'rejected');

        $this->assertSame(CctvApproval::REJECTED, CctvApproval::query()->where('adm_request_id', $request->id)->value('status'));

        Sanctum::actingAs($this->officer);
        $this->postJson(self::STAFF.$request->id.'/assign', ['assigned_to' => $this->officer->id])->assertOk();
        $this->postJson(self::STAFF.$request->id.'/resolve', ['resolution_summary' => 'Trying anyway.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cctv_approval']);
    }

    public function test_non_cctv_requests_do_not_need_approval_and_have_no_cctv_endpoint(): void
    {
        $request = $this->submit('general-administration');

        Sanctum::actingAs($this->approver);
        $this->postJson(self::STAFF.$request->id.'/cctv/approve')->assertNotFound();

        Sanctum::actingAs($this->officer);
        $this->postJson(self::STAFF.$request->id.'/assign', ['assigned_to' => $this->officer->id])->assertOk();
        $this->postJson(self::STAFF.$request->id.'/resolve', ['resolution_summary' => 'Handled.'])->assertOk();
    }

    public function test_requester_cannot_approve_their_own_cctv_request(): void
    {
        $manager = User::factory()->role(RoleSlug::AdministrationManager)->create();

        Sanctum::actingAs($manager);
        $category = AdmCategory::query()->where('slug', 'cctv-surveillance')->firstOrFail();
        $type = AdmRequestType::query()->where('adm_category_id', $category->id)->firstOrFail();
        $id = $this->postJson('/api/v1/administration/requests', [
            'category_id' => $category->id,
            'request_type_id' => $type->id,
            'subject' => 'My own footage request',
            'description' => 'Manager requesting footage for an audit.',
            'contact_phone' => '0771234567',
        ])->assertCreated()->json('data.id');

        $this->postJson(self::STAFF.$id.'/cctv/approve')->assertForbidden();
    }
}
