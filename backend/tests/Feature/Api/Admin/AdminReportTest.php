<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketVerification;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $technician;

    private User $supervisor;

    private User $administrator;

    private Branch $headOffice;

    private Branch $kandy;

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

        $this->headOffice = Branch::factory()->create(['name' => 'Head Office', 'code' => 'HO']);
        $this->kandy = Branch::factory()->create(['name' => 'Kandy Branch', 'code' => 'BR-KAN']);

        $this->employee = User::factory()->role(RoleSlug::Employee)->create([
            'branch_id' => $this->headOffice->id,
        ]);
        $this->technician = User::factory()->role(RoleSlug::Technician)->create([
            'full_name' => 'Kavindi Silva',
            'branch_id' => $this->headOffice->id,
        ]);
        $this->supervisor = User::factory()->role(RoleSlug::Supervisor)->create([
            'branch_id' => $this->headOffice->id,
        ]);
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create([
            'branch_id' => $this->headOffice->id,
        ]);

        $this->seedTickets();
    }

    public function test_an_employee_cannot_view_reports(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/admin/reports')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_a_technician_cannot_view_or_export_reports(): void
    {
        Sanctum::actingAs($this->technician);

        $this->getJson('/api/v1/admin/reports')
            ->assertForbidden()
            ->assertJson(['code' => 'FORBIDDEN']);

        $this->get('/api/v1/admin/reports/export?format=csv')
            ->assertForbidden();
    }

    public function test_a_supervisor_can_view_reports_but_cannot_export_them(): void
    {
        Sanctum::actingAs($this->supervisor);

        $this->getJson('/api/v1/admin/reports?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.ticket_volume', 4);

        $this->get('/api/v1/admin/reports/export?format=csv')
            ->assertForbidden()
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_an_administrator_receives_aggregated_metrics_not_ticket_rows(): void
    {
        Sanctum::actingAs($this->administrator);

        $response = $this->getJson('/api/v1/admin/reports?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk();

        $response
            ->assertJsonPath('data.kpis.ticket_volume', 4)
            ->assertJsonPath('data.kpis.closed_tickets', 2)
            ->assertJsonPath('data.kpis.reopened_tickets', 1)
            ->assertJsonPath('data.kpis.critical_tickets', 1)
            ->assertJsonPath('data.kpis.verification.issued', 1)
            ->assertJsonPath('data.kpis.verification.verified', 1)
            ->assertJsonPath('data.kpis.verification.completion_rate', 100)
            ->assertJsonPath('data.kpis.average_first_response.sample_size', 2)
            ->assertJsonPath('data.kpis.average_resolution.sample_size', 2)
            ->assertJsonMissingPath('data.tickets')
            ->assertJsonMissingPath('data.recent_tickets');

        $this->assertIsArray($response->json('data.charts.volume'));
        $this->assertIsArray($response->json('data.charts.by_branch'));
        $this->assertIsArray($response->json('data.charts.by_category'));
        $this->assertIsArray($response->json('data.charts.by_priority'));
        $this->assertIsArray($response->json('data.charts.by_status'));
        $this->assertIsArray($response->json('data.charts.by_staff'));
        $this->assertIsArray($response->json('data.charts.aging'));
        $this->assertIsArray($response->json('data.charts.verification'));

        $payload = $response->getContent();
        $this->assertStringNotContainsString('password', strtolower($payload));
        $this->assertStringNotContainsString('ChangeMe@2026', $payload);
    }

    public function test_report_filters_are_applied_in_the_database(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->getJson('/api/v1/admin/reports?date_from=2026-09-01&date_to=2026-09-30&branch_id='.$this->kandy->id)
            ->assertOk()
            ->assertJsonPath('data.kpis.ticket_volume', 1)
            ->assertJsonPath('data.kpis.critical_tickets', 1);

        $this->getJson('/api/v1/admin/reports?date_from=2026-09-01&date_to=2026-09-30&assigned_to='.$this->technician->id)
            ->assertOk()
            ->assertJsonPath('data.kpis.ticket_volume', 2);
    }

    public function test_an_administrator_can_export_csv_and_pdf(): void
    {
        Sanctum::actingAs($this->administrator);

        $csv = $this->get('/api/v1/admin/reports/export?format=csv&date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk();

        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', (string) $csv->headers->get('content-disposition'));
        $csvBody = $csv->streamedContent();
        $this->assertStringContainsString('Ticket volume', $csvBody);
        $this->assertStringContainsString('Tickets by branch', $csvBody);
        $this->assertStringContainsString('Verification completion', $csvBody);
        $this->assertStringContainsString('Ticket details', $csvBody);
        $this->assertStringContainsString('Requester', $csvBody);
        $this->assertStringContainsString('Description', $csvBody);
        $this->assertStringContainsString('Closed hardware ticket', $csvBody);
        $this->assertStringContainsString('Critical Kandy outage', $csvBody);
        $this->assertStringContainsString($this->employee->full_name, $csvBody);
        $this->assertStringContainsString('Docking station light fails after power cut', $csvBody);
        $this->assertStringContainsString('Replaced power brick and confirmed boot', $csvBody);

        $pdf = $this->get('/api/v1/admin/reports/export?format=pdf&date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk();

        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'report.exported',
            'user_id' => $this->administrator->id,
        ]);
    }

    public function test_export_rejects_unknown_formats(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->getJson('/api/v1/admin/reports/export?format=xlsx')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }

    private function seedTickets(): void
    {
        $closed = TicketStatus::query()->where('slug', 'closed')->firstOrFail();
        $open = TicketStatus::query()->where('slug', 'new')->firstOrFail();
        $inProgress = TicketStatus::query()->where('slug', 'in-progress')->firstOrFail();
        $critical = TicketPriority::query()->where('slug', 'critical')->firstOrFail();
        $medium = TicketPriority::query()->where('slug', 'medium')->firstOrFail();
        $hardware = TicketCategory::query()->where('slug', 'hardware')->firstOrFail();
        $software = TicketCategory::query()->where('slug', 'software')->firstOrFail();

        Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Closed hardware ticket',
            'description' => 'Docking station light fails after power cut',
            'additional_details' => 'Asset HO-LAP-014',
            'contact_location' => 'Desk B-14',
            'preferred_contact_method' => 'email',
            'resolution_summary' => 'Replaced power brick and confirmed boot',
            'resolved_by' => $this->technician->id,
            'branch_id' => $this->headOffice->id,
            'category_id' => $hardware->id,
            'priority_id' => $medium->id,
            'status_id' => $closed->id,
            'assigned_to' => $this->technician->id,
            'first_responded_at' => '2026-09-02 09:00:00',
            'resolved_at' => '2026-09-03 11:00:00',
            'verified_at' => '2026-09-03 15:00:00',
            'closed_at' => '2026-09-03 15:00:00',
            'created_at' => '2026-09-02 08:00:00',
        ]);

        $verified = Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Verified software ticket',
            'description' => 'Payroll application freezes on save',
            'branch_id' => $this->headOffice->id,
            'category_id' => $software->id,
            'priority_id' => $medium->id,
            'status_id' => $closed->id,
            'assigned_to' => $this->technician->id,
            'first_responded_at' => '2026-09-10 10:30:00',
            'resolved_at' => '2026-09-10 16:00:00',
            'verified_at' => '2026-09-10 17:00:00',
            'closed_at' => '2026-09-10 17:00:00',
            'is_reopened' => true,
            'reopen_count' => 1,
            'created_at' => '2026-09-10 10:00:00',
        ]);

        TicketVerification::query()->create([
            'ticket_id' => $verified->id,
            'code_hash' => hash('sha256', 'not-a-real-code'),
            'code_last_four' => '0000',
            'issued_by' => $this->technician->id,
            'issued_to' => $this->employee->id,
            'status' => 'verified',
            'issued_at' => '2026-09-10 16:00:00',
            'expires_at' => '2026-09-10 20:00:00',
            'verified_at' => '2026-09-10 17:00:00',
        ]);

        Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Open request',
            'branch_id' => $this->headOffice->id,
            'category_id' => $hardware->id,
            'priority_id' => $medium->id,
            'status_id' => $open->id,
            'created_at' => '2026-09-12 09:00:00',
        ]);

        Ticket::factory()->forRequester($this->employee)->create([
            'subject' => 'Critical Kandy outage',
            'description' => 'Branch network offline since morning briefing',
            'branch_id' => $this->kandy->id,
            'category_id' => $software->id,
            'priority_id' => $critical->id,
            'status_id' => $inProgress->id,
            'created_at' => '2026-09-15 09:00:00',
        ]);
    }
}
