<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tickets;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketAttachment;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateTicketTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/tickets';

    private User $employee;

    private TicketCategory $category;

    private TicketPriority $priority;

    private TicketStatus $openStatus;

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
        $this->category = TicketCategory::query()->where('slug', 'software')->firstOrFail();
        $this->priority = TicketPriority::query()->where('slug', 'high')->firstOrFail();
        $this->openStatus = TicketStatus::query()->where('is_default', true)->where('is_open', true)->firstOrFail();
    }

    public function test_guests_cannot_create_a_ticket(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_an_employee_can_create_a_ticket(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload([
            'subject' => 'Laptop will not power on',
            'description' => 'The docking station light is off and the battery does not charge.',
            'contact_location' => 'Desk B-14',
            'preferred_contact_method' => 'email',
            'additional_details' => 'Happened after yesterday’s power cut.',
        ]))->assertCreated();

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Your IT support request has been submitted.');
        $response->assertJsonPath('data.subject', 'Laptop will not power on');
        $response->assertJsonPath('data.requester.id', $this->employee->id);
        $response->assertJsonPath('data.status.id', $this->openStatus->id);
        $response->assertJsonPath('data.status.is_open', true);
        $response->assertJsonPath('data.category.id', $this->category->id);
        $response->assertJsonPath('data.priority.id', $this->priority->id);
        $response->assertJsonPath('data.assignee', null);

        $reference = $response->json('data.reference');
        $this->assertMatchesRegularExpression('/^IT-'.now()->year.'-\d{6}$/', $reference);

        $this->assertDatabaseHas('tickets', [
            'reference' => $reference,
            'requester_id' => $this->employee->id,
            'branch_id' => $this->employee->branch_id,
            'status_id' => $this->openStatus->id,
            'subject' => 'Laptop will not power on',
        ]);
    }

    public function test_an_hr_officer_can_create_an_it_ticket_as_an_employee(): void
    {
        $officer = User::factory()->role(RoleSlug::HrOfficer)->create();

        Sanctum::actingAs($officer);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload([
            'subject' => 'HR laptop Wi-Fi keeps dropping',
        ]))->assertCreated();

        $response->assertJsonPath('data.requester.id', $officer->id);
        $this->assertDatabaseHas('tickets', [
            'reference' => $response->json('data.reference'),
            'requester_id' => $officer->id,
            'subject' => 'HR laptop Wi-Fi keeps dropping',
        ]);
    }

    public function test_an_hr_manager_can_create_an_it_ticket_as_an_employee(): void
    {
        $manager = User::factory()->role(RoleSlug::HrManager)->create();

        Sanctum::actingAs($manager);

        $this->postJson(self::ENDPOINT, $this->validPayload([
            'subject' => 'Need access to shared HR printer',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.requester.id', $manager->id);
    }

    public function test_new_tickets_are_assigned_to_every_it_staff_member(): void
    {
        $technician = User::factory()->role(RoleSlug::Technician)->create();
        $administrator = User::factory()->role(RoleSlug::Administrator)->create();

        Sanctum::actingAs($this->employee);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload([
            'subject' => 'Shared printer is offline',
        ]))->assertCreated();

        $id = $response->json('data.id');

        $this->assertTrue($response->json('data.assigned_to_team'));
        $this->assertSame($administrator->id, $response->json('data.assignee.id'));
        $this->assertSame('assigned', $response->json('data.status.slug'));

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $id,
            'assigned_to' => $technician->id,
            'released_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $id,
            'assigned_to' => $administrator->id,
            'released_at' => null,
        ]);
    }

    public function test_ticket_numbers_are_unique_and_sequential(): void
    {
        Sanctum::actingAs($this->employee);

        $first = $this->postJson(self::ENDPOINT, $this->validPayload(['subject' => 'First request ticket']))
            ->assertCreated()
            ->json('data.reference');

        $second = $this->postJson(self::ENDPOINT, $this->validPayload(['subject' => 'Second request ticket']))
            ->assertCreated()
            ->json('data.reference');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, $this->sequenceOf($first));
        $this->assertSame(2, $this->sequenceOf($second));
        $this->assertCount(2, Ticket::query()->pluck('reference')->unique());
    }

    public function test_an_audit_log_is_written_for_the_creator(): void
    {
        Sanctum::actingAs($this->employee);

        $id = $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertCreated()
            ->json('data.id');

        $this->assertTrue(
            AuditLog::query()
                ->where('event', 'ticket.created')
                ->where('user_id', $this->employee->id)
                ->where('auditable_id', $id)
                ->exists(),
        );
    }

    public function test_clients_cannot_choose_ownership_status_or_reference(): void
    {
        $colleague = User::factory()->role(RoleSlug::Employee)->create();
        $closed = TicketStatus::query()->where('slug', 'closed')->firstOrFail();

        Sanctum::actingAs($this->employee);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload([
            'requester_id' => $colleague->id,
            'assigned_to' => $colleague->id,
            'status_id' => $closed->id,
            'reference' => 'IT-2026-999999',
        ]))->assertCreated();

        $this->assertSame($this->employee->id, $response->json('data.requester.id'));
        $this->assertSame($this->openStatus->id, $response->json('data.status.id'));
        $this->assertNull($response->json('data.assignee'));
        $this->assertNotSame('IT-2026-999999', $response->json('data.reference'));
    }

    public function test_required_fields_are_validated(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson(self::ENDPOINT, [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['subject', 'category_id', 'priority_id', 'branch_id', 'contact_phone']);
    }

    public function test_an_inactive_or_unknown_priority_is_rejected(): void
    {
        $this->priority->update(['is_active' => false]);

        Sanctum::actingAs($this->employee);

        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority_id']);

        $this->postJson(self::ENDPOINT, $this->validPayload(['priority_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority_id']);
    }

    public function test_an_invented_contact_method_is_rejected(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson(self::ENDPOINT, $this->validPayload([
            'preferred_contact_method' => 'carrier-pigeon',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['preferred_contact_method']);
    }

    public function test_an_attachment_is_stored_with_a_generated_filename(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->employee);

        $file = UploadedFile::fake()->image('screenshot.png', 40, 40);

        $response = $this->post(self::ENDPOINT, $this->validPayload([
            'attachments' => [$file],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonPath('data.attachments.0.original_name', 'screenshot.png');
        $this->assertStringNotContainsString('path', mb_strtolower($response->getContent()));
        $this->assertStringNotContainsString('storage/app', $response->getContent());

        $attachment = TicketAttachment::query()->firstOrFail();
        $this->assertTrue(Str::isUuid(pathinfo($attachment->path, PATHINFO_FILENAME)));
        $this->assertSame('png', pathinfo($attachment->path, PATHINFO_EXTENSION));
        $this->assertNotSame('screenshot.png', basename($attachment->path));
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_executable_uploads_are_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->employee);

        $this->post(self::ENDPOINT, $this->validPayload([
            'attachments' => [UploadedFile::fake()->create('payload.exe', 20, 'application/x-msdownload')],
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments.0']);

        $this->assertDatabaseCount('ticket_attachments', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_oversized_uploads_are_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->employee);

        $this->post(self::ENDPOINT, $this->validPayload([
            'attachments' => [UploadedFile::fake()->create('huge.pdf', 20_000, 'application/pdf')],
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments.0']);
    }

    public function test_only_the_ticket_owner_can_download_an_attachment(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->employee);

        $created = $this->post(self::ENDPOINT, $this->validPayload([
            'attachments' => [UploadedFile::fake()->image('desk.jpg', 20, 20)],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $ticketId = $created->json('data.id');
        $attachmentId = $created->json('data.attachments.0.id');
        $url = '/api/v1/tickets/'.$ticketId.'/attachments/'.$attachmentId;

        $this->get($url)->assertOk();

        $colleague = User::factory()->role(RoleSlug::Employee)->create();
        Sanctum::actingAs($colleague);

        $this->getJson($url)
            ->assertForbidden()
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_the_owner_can_attach_a_file_to_an_open_ticket(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->employee);

        $created = $this->postJson(self::ENDPOINT, $this->validPayload())->assertCreated();
        $ticketId = $created->json('data.id');

        $this->post('/api/v1/tickets/'.$ticketId.'/attachments', [
            'attachments' => [UploadedFile::fake()->image('follow-up.png', 24, 24)],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.0.original_name', 'follow-up.png');

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticketId,
            'original_name' => 'follow-up.png',
        ]);
    }

    public function test_guests_cannot_download_an_attachment(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->employee);

        $created = $this->post(self::ENDPOINT, $this->validPayload([
            'attachments' => [UploadedFile::fake()->image('notes.jpg', 20, 20)],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/tickets/'.$created->json('data.id').'/attachments/'.$created->json('data.attachments.0.id'))
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_lookups_expose_categories_and_priorities_from_the_database(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/lookups')->assertOk();

        $categoryNames = collect($response->json('data.categories'))->pluck('name');
        $priorityNames = collect($response->json('data.priorities'))->pluck('name');

        $this->assertTrue($categoryNames->contains('Hardware'));
        $this->assertTrue($categoryNames->contains('Printer'));
        $this->assertTrue($priorityNames->contains('Low'));
        $this->assertTrue($priorityNames->contains('Critical'));
        $this->assertNotEmpty($response->json('data.attachments.allowed_extensions'));
    }

    public function test_an_employee_can_create_a_ticket_for_another_branch(): void
    {
        $otherBranch = \App\Models\Branch::factory()->create(['is_active' => true]);

        Sanctum::actingAs($this->employee);

        $this->postJson(self::ENDPOINT, $this->validPayload([
            'subject' => 'Printer jam at another site',
            'branch_id' => $otherBranch->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.branch.id', $otherBranch->id);

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Printer jam at another site',
            'requester_id' => $this->employee->id,
            'branch_id' => $otherBranch->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Unable to open the shared drive',
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'branch_id' => $this->employee->branch_id,
            'contact_phone' => '0771234567',
            'description' => 'The finance share is not mapped after this morning’s restart.',
        ], $overrides);
    }

    private function sequenceOf(string $reference): int
    {
        $this->assertMatchesRegularExpression('/^IT-\d{4}-\d{6}$/', $reference);

        return (int) substr($reference, -6);
    }
}
