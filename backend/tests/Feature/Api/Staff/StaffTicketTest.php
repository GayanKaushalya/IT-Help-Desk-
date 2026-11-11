<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Staff;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $colleague;

    private User $technician;

    private User $administrator;

    private Ticket $openTicket;

    private Ticket $assignedTicket;

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
        $this->colleague = User::factory()->role(RoleSlug::Employee)->create();
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();

        $this->openTicket = Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Printer jam on the third floor',
        ]);
        $this->assignedTicket = Ticket::factory()
            ->forRequester($this->colleague)
            ->assignedTo($this->technician)
            ->status('in-progress')
            ->create([
                'subject' => 'VPN drops every hour',
            ]);
    }

    public function test_an_employee_cannot_reach_the_staff_queue(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/staff/tickets')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_staff_can_list_every_ticket(): void
    {
        Sanctum::actingAs($this->technician);

        $response = $this->getJson('/api/v1/staff/tickets')->assertOk();
        $subjects = collect($response->json('data'))->pluck('subject');

        $this->assertTrue($subjects->contains('Printer jam on the third floor'));
        $this->assertTrue($subjects->contains('VPN drops every hour'));
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    public function test_assigned_queue_only_returns_the_current_agents_tickets(): void
    {
        Sanctum::actingAs($this->technician);

        $response = $this->getJson('/api/v1/staff/tickets/assigned')->assertOk();
        $subjects = collect($response->json('data'))->pluck('subject');

        $this->assertTrue($subjects->contains('VPN drops every hour'));
        $this->assertFalse($subjects->contains('Printer jam on the third floor'));
    }

    public function test_an_administrator_can_assign_a_ticket_to_it_support(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/assign', [
            'assigned_to' => $this->technician->id,
            'note' => 'Hardware queue',
        ])->assertOk()
            ->assertJsonPath('data.assignee.id', $this->technician->id)
            ->assertJsonPath('data.status.slug', 'assigned');

        $this->assertDatabaseHas('tickets', [
            'id' => $this->openTicket->id,
            'assigned_to' => $this->technician->id,
        ]);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $this->openTicket->id,
            'assigned_to' => $this->technician->id,
            'assigned_by' => $this->administrator->id,
        ]);
    }

    public function test_a_technician_cannot_assign_a_ticket_to_someone_else(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/assign', [
            'assigned_to' => $this->administrator->id,
        ])->assertForbidden()->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_a_technician_can_take_an_unassigned_ticket(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/assign', [
            'assigned_to' => $this->technician->id,
        ])->assertOk()
            ->assertJsonPath('data.assignee.id', $this->technician->id)
            ->assertJsonPath('data.status.slug', 'assigned');
    }

    public function test_a_ticket_cannot_be_assigned_to_an_employee(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/assign', [
            'assigned_to' => $this->employee->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_status_changes_follow_the_workflow(): void
    {
        Sanctum::actingAs($this->technician);

        $resolved = TicketStatus::query()->where('slug', 'resolved')->firstOrFail();

        $this->patchJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/status', [
            'status_id' => $resolved->id,
            'resolution_summary' => 'Replaced the VPN profile and confirmed the tunnel stays up.',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'resolved')
            ->assertJsonPath('data.awaiting_verification', true);

        $this->patchJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/status', [
            'status_id' => TicketStatus::query()->where('slug', 'in-progress')->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['status_id']);
    }

    public function test_an_open_ticket_can_be_resolved_and_issues_verification(): void
    {
        Sanctum::actingAs($this->technician);

        $response = $this->patchJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/status', [
            'status_id' => TicketStatus::query()->where('slug', 'resolved')->value('id'),
            'resolution_summary' => 'Cleared the jam and printed a test page.',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'resolved')
            ->assertJsonPath('data.awaiting_verification', true)
            ->assertJsonPath('data.assignee.id', $this->technician->id);

        $code = $response->json('data.verification_code');
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_assigned_status_claims_the_ticket_when_unassigned(): void
    {
        Sanctum::actingAs($this->technician);

        $this->patchJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/status', [
            'status_id' => TicketStatus::query()->where('slug', 'assigned')->value('id'),
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'assigned')
            ->assertJsonPath('data.assignee.id', $this->technician->id);
    }

    public function test_staff_can_resolve_a_ticket_that_is_in_progress(): void
    {
        Sanctum::actingAs($this->technician);

        $response = $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/resolve', [
            'resolution_summary' => 'Replaced the VPN profile and confirmed the tunnel stays up.',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'resolved')
            ->assertJsonPath('data.resolution_summary', 'Replaced the VPN profile and confirmed the tunnel stays up.')
            ->assertJsonPath('data.awaiting_verification', true);

        $code = $response->json('data.verification_code');
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $show = $this->getJson('/api/v1/staff/tickets/'.$this->assignedTicket->id)->assertOk();
        $this->assertSame($code, $show->json('data.verification_code'));
        $this->assertSame($code, $show->json('data.verification.code'));
        $this->assertSame('pending', $show->json('data.verification.status'));
    }

    public function test_staff_can_change_priority_when_authorised(): void
    {
        Sanctum::actingAs($this->technician);

        $critical = TicketPriority::query()->where('slug', 'critical')->firstOrFail();

        $this->patchJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/priority', [
            'priority_id' => $critical->id,
        ])->assertOk()->assertJsonPath('data.priority.slug', 'critical');
    }

    public function test_internal_notes_are_visible_to_staff_and_hidden_from_employees(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/comments', [
            'body' => 'Swap the firewall rule before calling the user.',
            'is_internal' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_internal', true)
            ->assertJsonPath('data.body', 'Swap the firewall rule before calling the user.');

        $staffShow = $this->getJson('/api/v1/staff/tickets/'.$this->assignedTicket->id)->assertOk();
        $this->assertTrue(collect($staffShow->json('data.comments'))->contains(
            fn (array $comment): bool => ($comment['is_internal'] ?? false)
                && $comment['body'] === 'Swap the firewall rule before calling the user.',
        ));

        Sanctum::actingAs($this->colleague);

        $employeeShow = $this->getJson('/api/v1/tickets/'.$this->assignedTicket->id)->assertOk();
        $this->assertFalse(collect($employeeShow->json('data.comments'))->contains(
            fn (array $comment): bool => str_contains((string) ($comment['body'] ?? ''), 'firewall'),
        ));
        $this->assertStringNotContainsString('is_internal', $employeeShow->getContent());
        $this->assertStringNotContainsString('firewall rule', $employeeShow->getContent());
    }

    public function test_an_employee_cannot_create_an_internal_note_on_the_staff_route(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/comments', [
            'body' => 'Trying to hide this from myself.',
            'is_internal' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('ticket_comments', [
            'ticket_id' => $this->openTicket->id,
            'body' => 'Trying to hide this from myself.',
        ]);
    }

    public function test_staff_can_add_a_public_comment_and_an_attachment(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/comments', [
            'body' => 'Please try the new VPN profile and reply here.',
        ])->assertCreated()
            ->assertJsonPath('data.is_internal', false);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->assignedTicket->id,
            'body' => 'Please try the new VPN profile and reply here.',
            'is_internal' => false,
        ]);

        $this->post('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/attachments', [
            'attachments' => [UploadedFile::fake()->image('screenshot.png', 32, 32)],
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_staff_show_includes_allowed_transitions(): void
    {
        Sanctum::actingAs($this->technician);

        $response = $this->getJson('/api/v1/staff/tickets/'.$this->assignedTicket->id)->assertOk();
        $slugs = collect($response->json('data.allowed_transitions'))->pluck('slug');

        $this->assertTrue($slugs->contains('resolved'));
        $this->assertFalse($slugs->contains('closed'));
        $this->assertFalse($slugs->contains('new'));
    }

    public function test_closing_as_verified_requires_an_individual_assignee(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/resolve', [
            'resolution_summary' => 'Fixed the VPN profile.',
        ])->assertOk();

        $this->assignedTicket->forceFill(['assigned_to' => null])->save();
        $this->assignedTicket->assignments()->current()->update(['released_at' => now()]);

        $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/close-as-verified')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);

        $this->assertSame('resolved', $this->assignedTicket->fresh()?->status?->slug);
    }

    public function test_closing_as_verified_succeeds_when_a_member_is_assigned(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/resolve', [
            'resolution_summary' => 'Fixed the VPN profile.',
        ])->assertOk();

        $this->postJson('/api/v1/staff/tickets/'.$this->assignedTicket->id.'/close-as-verified')
            ->assertOk()
            ->assertJsonPath('data.status.slug', 'closed')
            ->assertJsonPath('data.assignee.id', $this->technician->id);
    }

    public function test_resolving_a_team_ticket_claims_the_resolver(): void
    {
        Sanctum::actingAs($this->administrator);

        app(\App\Services\TicketWorkflowService::class)
            ->assignToAllStaff($this->openTicket, $this->administrator);

        $this->openTicket->refresh()->load('assignments');
        $this->assertTrue($this->openTicket->isAssignedToTeam());

        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/staff/tickets/'.$this->openTicket->id.'/resolve', [
            'resolution_summary' => 'Cleared the jam and printed a test page.',
        ])->assertOk()
            ->assertJsonPath('data.assignee.id', $this->technician->id)
            ->assertJsonPath('data.assigned_to_team', false);
    }
}
