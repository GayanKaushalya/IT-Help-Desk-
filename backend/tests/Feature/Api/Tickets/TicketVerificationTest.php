<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tickets;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketVerification;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $colleague;

    private User $technician;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('helpdesk.verification.max_attempts', 2);
        config()->set('helpdesk.verification.lockout_minutes', 15);

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
        $this->ticket = Ticket::factory()
            ->forRequester($this->employee)
            ->assignedTo($this->technician)
            ->status('in-progress')
            ->create();
    }

    public function test_resolving_issues_a_hashed_one_time_code_shown_only_once(): void
    {
        Sanctum::actingAs($this->technician);

        $response = $this->postJson('/api/v1/staff/tickets/'.$this->ticket->id.'/resolve', [
            'resolution_summary' => 'Replaced the docking station from stock.',
        ])->assertOk();

        $code = $response->json('data.verification_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $row = TicketVerification::query()->where('ticket_id', $this->ticket->id)->firstOrFail();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertTrue(Hash::check($code, $row->code_hash));
        $this->assertNull($row->code_last_four);
        $this->assertTrue($row->expires_at->isFuture());

        $staffShow = $this->getJson('/api/v1/staff/tickets/'.$this->ticket->id)->assertOk();
        $this->assertSame($code, $staffShow->json('data.verification_code'));
        $this->assertSame($code, $staffShow->json('data.verification.code'));
        $this->assertStringNotContainsString($row->code_hash, $staffShow->getContent());
        $this->assertSame('pending', $staffShow->json('data.verification.status'));
        $this->assertSame(0, $staffShow->json('data.verification.attempts'));
    }

    public function test_the_employee_sees_the_resolution_and_can_verify(): void
    {
        $code = $this->issueCode();

        Sanctum::actingAs($this->employee);

        $show = $this->getJson('/api/v1/tickets/'.$this->ticket->id)->assertOk();
        $this->assertTrue($show->json('data.awaiting_verification'));
        $this->assertSame('Replaced the docking station from stock.', $show->json('data.resolution_summary'));
        $this->assertSame($this->technician->id, $show->json('data.resolved_by.id'));
        $this->assertNotEmpty($show->json('data.resolved_at'));
        $this->assertArrayNotHasKey('verification_code', $show->json('data'));
        $this->assertArrayNotHasKey('attempts', $show->json('data.verification'));
        $this->assertStringNotContainsString($code, $show->getContent());

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', [
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'closed')
            ->assertJsonPath('data.awaiting_verification', false)
            ->assertJsonPath('data.verification.status', 'verified');

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'closed_by' => $this->employee->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ticket.verified',
            'auditable_id' => $this->ticket->id,
        ]);
    }

    public function test_an_invalid_code_returns_a_generic_error(): void
    {
        $this->issueCode();

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', [
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Invalid verification code.');
    }

    public function test_an_expired_or_used_code_returns_the_same_generic_error(): void
    {
        $code = $this->issueCode();

        TicketVerification::query()->where('ticket_id', $this->ticket->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', [
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Invalid verification code.');
    }

    public function test_verifying_again_after_success_is_idempotent(): void
    {
        $code = $this->issueCode();

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => $code])->assertOk();
        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.status.slug', 'closed')
            ->assertJsonPath('data.verification.status', 'verified');

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => '000000'])
            ->assertOk()
            ->assertJsonPath('data.status.slug', 'closed');
    }

    public function test_verification_is_blocked_after_too_many_failed_attempts(): void
    {
        $this->issueCode();

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => '111111'])->assertUnprocessable();
        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => '222222'])->assertUnprocessable();

        $blocked = $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => '333333'])
            ->assertUnprocessable();

        $this->assertSame(
            'Verification attempts are temporarily blocked. Please try again later.',
            $blocked->json('errors.code.0'),
        );
    }

    public function test_another_employee_cannot_verify_the_ticket(): void
    {
        $code = $this->issueCode();

        Sanctum::actingAs($this->colleague);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => $code])
            ->assertForbidden();
    }

    public function test_a_resolved_ticket_can_be_verified_even_if_status_flags_are_inconsistent(): void
    {
        $code = $this->issueCode();

        TicketStatus::query()->where('slug', 'resolved')->update([
            'requires_verification' => false,
            'is_resolved' => false,
        ]);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.status.slug', 'closed');
    }

    public function test_staff_cannot_close_a_ticket_without_verification(): void
    {
        $this->issueCode();

        Sanctum::actingAs($this->technician);

        $this->patchJson('/api/v1/staff/tickets/'.$this->ticket->id.'/status', [
            'status_id' => TicketStatus::query()->where('slug', 'closed')->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['status_id']);

        $this->assertSame('resolved', $this->ticket->fresh()?->status?->slug);
    }

    public function test_employee_cannot_confirm_close_without_an_individual_assignee(): void
    {
        $this->issueCode();

        $this->ticket->forceFill(['assigned_to' => null])->save();
        $this->ticket->assignments()->current()->update(['released_at' => now()]);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ticket->id.'/verify', [
            'decision' => 'confirmed',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);

        $this->assertSame('resolved', $this->ticket->fresh()?->status?->slug);
    }

    private function issueCode(): string
    {
        Sanctum::actingAs($this->technician);

        $response = $this->postJson('/api/v1/staff/tickets/'.$this->ticket->id.'/resolve', [
            'resolution_summary' => 'Replaced the docking station from stock.',
        ])->assertOk();

        return (string) $response->json('data.verification_code');
    }
}
