<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Notifications;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $technician;

    private User $administrator;

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
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();
    }

    public function test_creating_a_ticket_notifies_the_employee_and_it_staff(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/tickets', $this->ticketPayload())
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->employee->id,
            'type' => 'ticket.created',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->technician->id,
            'type' => 'ticket.new',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->administrator->id,
            'type' => 'ticket.new',
        ]);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.type', 'ticket.created');
    }

    public function test_assigning_a_ticket_notifies_the_requester_and_assignee(): void
    {
        $ticket = $this->openTicket();

        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/staff/tickets/'.$ticket->id.'/assign', [
            'assigned_to' => $this->technician->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->employee->id,
            'type' => 'ticket.assigned',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->technician->id,
            'type' => 'ticket.assigned',
        ]);
    }

    public function test_a_user_can_mark_notifications_read_but_not_someone_elses(): void
    {
        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/tickets', $this->ticketPayload())->assertCreated();

        $mine = UserNotification::query()->where('user_id', $this->employee->id)->firstOrFail();
        $theirs = UserNotification::query()->where('user_id', $this->technician->id)->firstOrFail();

        $this->postJson('/api/v1/notifications/'.$mine->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->postJson('/api/v1/notifications/'.$theirs->id.'/read')
            ->assertNotFound();

        Sanctum::actingAs($this->technician);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(): array
    {
        return [
            'subject' => 'VPN cannot connect from the branch',
            'description' => 'The VPN client fails after the password prompt.',
            'category_id' => TicketCategory::query()->where('slug', 'network')->value('id'),
            'priority_id' => TicketPriority::query()->where('slug', 'medium')->value('id'),
            'branch_id' => $this->employee->branch_id,
            'contact_phone' => '0771234567',
        ];
    }

    private function openTicket(): Ticket
    {
        return Ticket::factory()->forRequester($this->employee)->create([
            'status_id' => TicketStatus::query()->where('slug', 'new')->value('id'),
            'category_id' => TicketCategory::query()->where('slug', 'network')->value('id'),
            'priority_id' => TicketPriority::query()->where('slug', 'medium')->value('id'),
        ]);
    }
}
