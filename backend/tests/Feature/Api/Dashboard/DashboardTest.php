<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Dashboard;

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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/dashboard';

    private User $employee;

    private User $colleague;

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

        $this->employee = User::factory()->role(RoleSlug::Employee)->create([
            'full_name' => 'Nimal Perera',
        ]);
        $this->colleague = User::factory()->role(RoleSlug::Employee)->create([
            'full_name' => 'Kavindi Silva',
        ]);
    }

    public function test_guests_cannot_read_the_dashboard(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_an_employee_only_sees_their_own_tickets_and_counts(): void
    {
        $agent = User::factory()->role(RoleSlug::Technician)->create();

        Ticket::factory()->forRequester($this->employee)->status('new')->create();
        Ticket::factory()->forRequester($this->employee)->status('in-progress')->assignedTo($agent)->create();
        Ticket::factory()->forRequester($this->employee)->status('resolved')->assignedTo($agent)->create();
        Ticket::factory()->forRequester($this->employee)->status('closed')->assignedTo($agent)->create();
        Ticket::factory()->forRequester($this->colleague)->status('new')->create([
            'subject' => 'Secret colleague ticket',
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $response->assertJsonPath('data.statistics.total', 4);
        $response->assertJsonPath('data.statistics.open', 2);
        $response->assertJsonPath('data.statistics.in_progress', 1);
        $response->assertJsonPath('data.statistics.awaiting_verification', 1);
        $response->assertJsonPath('data.statistics.closed', 1);

        $subjects = collect($response->json('data.recent_tickets'))->pluck('subject');

        $this->assertCount(4, $subjects);
        $this->assertFalse($subjects->contains('Secret colleague ticket'));
        $this->assertStringNotContainsString('password', mb_strtolower($response->getContent()));
    }

    public function test_recent_tickets_include_the_fields_the_dashboard_needs(): void
    {
        $agent = User::factory()->role(RoleSlug::Technician)->create(['full_name' => 'Kavindi Silva']);

        $ticket = Ticket::factory()
            ->forRequester($this->employee)
            ->status('in-progress')
            ->assignedTo($agent)
            ->create(['subject' => 'Outlook cannot send mail']);

        Sanctum::actingAs($this->employee);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.recent_tickets.0.id', $ticket->id)
            ->assertJsonPath('data.recent_tickets.0.reference', $ticket->reference)
            ->assertJsonPath('data.recent_tickets.0.subject', 'Outlook cannot send mail')
            ->assertJsonPath('data.recent_tickets.0.status.slug', 'in-progress')
            ->assertJsonPath('data.recent_tickets.0.assignee.full_name', 'Kavindi Silva')
            ->assertJsonMissingPath('data.recent_tickets.0.description')
            ->assertJsonMissingPath('data.recent_tickets.0.assignee.password');
    }

    public function test_an_employee_with_no_tickets_gets_empty_counts(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.statistics.total', 0)
            ->assertJsonPath('data.recent_tickets', []);
    }
}
