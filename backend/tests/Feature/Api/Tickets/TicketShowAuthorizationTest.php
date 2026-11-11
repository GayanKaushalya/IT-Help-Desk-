<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tickets;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketShowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $colleague;

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

        $this->ownTicket = Ticket::factory()->forRequester($this->employee)->create();
        $this->foreignTicket = Ticket::factory()->forRequester($this->colleague)->create();
    }

    public function test_an_employee_can_open_their_own_ticket(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/tickets/'.$this->ownTicket->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->ownTicket->id)
            ->assertJsonPath('data.reference', $this->ownTicket->reference)
            ->assertJsonPath('data.requester.id', $this->employee->id);

        $this->assertStringNotContainsString('password', mb_strtolower(
            $this->getJson('/api/v1/tickets/'.$this->ownTicket->id)->getContent()
        ));
    }

    public function test_an_employee_cannot_open_another_requesters_ticket_by_id(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/tickets/'.$this->foreignTicket->id)
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_an_employee_cannot_open_another_requesters_ticket_by_reference(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/tickets/'.$this->foreignTicket->reference)
            ->assertForbidden()
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_an_unknown_ticket_is_not_found(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/tickets/999999')
            ->assertNotFound()
            ->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_guests_cannot_open_a_ticket(): void
    {
        $this->getJson('/api/v1/tickets/'.$this->ownTicket->id)
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }
}
