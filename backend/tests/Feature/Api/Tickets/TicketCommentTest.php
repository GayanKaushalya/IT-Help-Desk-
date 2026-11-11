<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tickets;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $colleague;

    private User $agent;

    private Ticket $ownTicket;

    private Ticket $foreignTicket;

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
        $this->agent = User::factory()->role(RoleSlug::Technician)->create();

        $this->ownTicket = Ticket::factory()->forRequester($this->employee)->assignedTo($this->agent)->create();
        $this->foreignTicket = Ticket::factory()->forRequester($this->colleague)->create();
    }

    public function test_an_employee_can_comment_on_their_own_ticket(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ownTicket->id.'/comments', [
            'body' => 'The docking station is still dark this morning.',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'The docking station is still dark this morning.')
            ->assertJsonPath('data.author.id', $this->employee->id)
            ->assertJsonPath('data.author.is_staff', false);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->ownTicket->id,
            'author_id' => $this->employee->id,
            'is_internal' => false,
        ]);
    }

    public function test_an_employee_cannot_comment_on_another_requesters_ticket(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->foreignTicket->id.'/comments', [
            'body' => 'Trying to reply on someone else\'s ticket.',
        ])->assertForbidden()->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_internal_notes_are_hidden_from_the_requester(): void
    {
        TicketComment::query()->create([
            'ticket_id' => $this->ownTicket->id,
            'author_id' => $this->agent->id,
            'body' => 'Visible update from IT support.',
        ]);
        TicketComment::query()->create([
            'ticket_id' => $this->ownTicket->id,
            'author_id' => $this->agent->id,
            'body' => 'Internal note: swap the docking station from stock.',
            'is_internal' => true,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/tickets/'.$this->ownTicket->id)->assertOk();
        $bodies = collect($response->json('data.comments'))->pluck('body');

        $this->assertTrue($bodies->contains('Visible update from IT support.'));
        $this->assertFalse($bodies->contains('Internal note: swap the docking station from stock.'));
        $this->assertStringNotContainsString('is_internal', $response->getContent());
        $this->assertStringNotContainsString('swap the docking station', $response->getContent());
    }

    public function test_the_detail_payload_includes_timeline_and_hides_status_controls(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/tickets/'.$this->ownTicket->id)->assertOk();

        $this->assertNotEmpty($response->json('data.timeline'));
        $this->assertSame('Ticket Created', $response->json('data.timeline.0.title'));
        $this->assertSame($this->employee->id, $response->json('data.requester.id'));
        $this->assertArrayNotHasKey('status_id', $response->json('data'));
    }

    public function test_clients_cannot_create_an_internal_note_through_the_public_comment_route(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets/'.$this->ownTicket->id.'/comments', [
            'body' => 'Please treat this as an internal note.',
            'is_internal' => true,
        ])->assertCreated()->assertJsonPath('data.body', 'Please treat this as an internal note.');

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->ownTicket->id,
            'body' => 'Please treat this as an internal note.',
            'is_internal' => false,
        ]);
    }
}
