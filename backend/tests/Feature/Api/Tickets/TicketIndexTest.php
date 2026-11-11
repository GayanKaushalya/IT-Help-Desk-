<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tickets;

use App\Enums\RoleSlug;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketIndexTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/tickets';

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

        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->colleague = User::factory()->role(RoleSlug::Employee)->create();
    }

    public function test_guests_cannot_list_tickets(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_an_employee_only_sees_their_own_tickets(): void
    {
        Ticket::factory()->forRequester($this->employee)->count(2)->create();
        Ticket::factory()->forRequester($this->colleague)->create([
            'subject' => 'Secret colleague ticket',
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $subjects = collect($response->json('data'))->pluck('subject');

        $this->assertCount(2, $subjects);
        $this->assertFalse($subjects->contains('Secret colleague ticket'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_the_list_is_paginated_and_filterable(): void
    {
        $high = TicketPriority::query()->where('slug', 'high')->firstOrFail();
        $low = TicketPriority::query()->where('slug', 'low')->firstOrFail();
        $open = TicketStatus::query()->where('slug', 'new')->firstOrFail();
        $closed = TicketStatus::query()->where('slug', 'closed')->firstOrFail();
        $hardware = TicketCategory::query()->where('slug', 'hardware')->firstOrFail();

        Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Laptop battery failure',
            'priority_id' => $high->id,
            'status_id' => $open->id,
            'category_id' => $hardware->id,
            'created_at' => now()->subDays(2),
        ]);
        Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Keyboard replacement',
            'priority_id' => $low->id,
            'status_id' => $closed->id,
            'category_id' => $hardware->id,
            'created_at' => now()->subDays(10),
        ]);
        Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Another open hardware issue',
            'priority_id' => $high->id,
            'status_id' => $open->id,
            'category_id' => $hardware->id,
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->employee);

        $this->getJson(self::ENDPOINT.'?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 3);

        $filtered = $this->getJson(self::ENDPOINT.'?'.http_build_query([
            'search' => 'Laptop',
            'status_id' => $open->id,
            'priority_id' => $high->id,
            'category_id' => $hardware->id,
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertOk();

        $this->assertCount(1, $filtered->json('data'));
        $this->assertSame('Laptop battery failure', $filtered->json('data.0.subject'));
    }

    public function test_a_technician_still_only_lists_tickets_they_raised(): void
    {
        $technician = User::factory()->role(RoleSlug::Technician)->create();

        Ticket::factory()->forRequester($this->employee)->create(['subject' => 'Employee request']);
        Ticket::factory()->forRequester($technician)->create(['subject' => 'Technician own request']);

        Sanctum::actingAs($technician);

        $subjects = collect($this->getJson(self::ENDPOINT)->assertOk()->json('data'))->pluck('subject');

        $this->assertTrue($subjects->contains('Technician own request'));
        $this->assertFalse($subjects->contains('Employee request'));
    }
}
