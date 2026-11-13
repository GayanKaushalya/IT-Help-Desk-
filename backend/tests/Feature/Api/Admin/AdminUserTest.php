<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\Ticket;
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

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $technician;

    private User $administrator;

    private User $superAdmin;

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
            'username' => 'nimal.p',
            'employee_id' => 'EMP-2001',
        ]);
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();
        $this->superAdmin = User::factory()->role(RoleSlug::SuperAdmin)->create();
    }

    public function test_an_employee_cannot_list_users(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/admin/users')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_a_technician_can_view_users_but_cannot_create_them(): void
    {
        Sanctum::actingAs($this->technician);

        $list = $this->getJson('/api/v1/admin/users')->assertOk();
        $this->assertGreaterThanOrEqual(4, $list->json('meta.total'));
        $this->assertArrayNotHasKey('password', $list->json('data.0'));
        $this->assertStringNotContainsString('Password@123', $list->getContent());

        $this->postJson('/api/v1/admin/users', $this->validUserPayload())
            ->assertForbidden();
    }

    public function test_an_administrator_can_filter_and_search_users(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->getJson('/api/v1/admin/users?search=nimal&account_status=active')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'nimal.p')
            ->assertJsonPath('data.0.account_status', 'active');

        $roleId = Role::query()->where('slug', RoleSlug::Employee->value)->value('id');

        $this->getJson('/api/v1/admin/users?role_id='.$roleId)
            ->assertOk()
            ->assertJsonPath('data.0.role.slug', 'employee');
    }

    public function test_an_administrator_can_create_a_user_and_sees_the_password_only_once(): void
    {
        Sanctum::actingAs($this->administrator);

        $response = $this->postJson('/api/v1/admin/users', $this->validUserPayload())
            ->assertCreated()
            ->assertJsonPath('data.username', 's.fernando')
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonMissingPath('data.password');

        $temporary = $response->json('data.temporary_password');
        $this->assertIsString($temporary);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9!@#$%&*?]{14}$/', $temporary);

        $created = User::query()->where('username', 's.fernando')->firstOrFail();
        $this->assertTrue(Hash::check($temporary, $created->password));
        $this->assertNotSame($temporary, $created->password);

        $show = $this->getJson('/api/v1/admin/users/'.$created->id)->assertOk();
        $this->assertArrayNotHasKey('temporary_password', $show->json('data'));
        $this->assertArrayNotHasKey('password', $show->json('data'));
        $this->assertStringNotContainsString($temporary, $show->getContent());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'auditable_id' => $created->id,
        ]);
    }

    public function test_an_administrator_can_assign_the_administrator_role(): void
    {
        Sanctum::actingAs($this->administrator);

        $adminRole = Role::query()->where('slug', RoleSlug::Administrator->value)->firstOrFail();

        $this->postJson('/api/v1/admin/users', $this->validUserPayload([
            'username' => 'peer.admin',
            'employee_id' => 'EMP-8802',
            'epf_number' => 'EPF-8802',
            'role_id' => $adminRole->id,
        ]))->assertCreated()->assertJsonPath('data.role.slug', 'administrator');
    }

    public function test_an_administrator_cannot_assign_super_admin(): void
    {
        Sanctum::actingAs($this->administrator);

        $superAdminRole = Role::query()->where('slug', RoleSlug::SuperAdmin->value)->firstOrFail();

        $this->postJson('/api/v1/admin/users', $this->validUserPayload([
            'role_id' => $superAdminRole->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['role_id']);
    }

    public function test_a_super_admin_can_create_an_administrator(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $adminRole = Role::query()->where('slug', RoleSlug::Administrator->value)->firstOrFail();

        $this->postJson('/api/v1/admin/users', $this->validUserPayload([
            'username' => 'new.admin',
            'employee_id' => 'EMP-8801',
            'epf_number' => 'EPF-8801',
            'role_id' => $adminRole->id,
        ]))->assertCreated()->assertJsonPath('data.role.slug', 'administrator');
    }

    public function test_user_detail_includes_ticket_history_and_hides_audit_from_technicians(): void
    {
        Ticket::factory()->forRequester($this->employee)->create(['subject' => 'Printer jam']);

        Sanctum::actingAs($this->technician);

        $techShow = $this->getJson('/api/v1/admin/users/'.$this->employee->id)->assertOk();
        $this->assertSame(1, $techShow->json('data.ticket_statistics.total'));
        $this->assertSame('Printer jam', $techShow->json('data.recent_tickets.0.subject'));
        $this->assertArrayNotHasKey('audit_logs', $techShow->json('data'));
        $this->assertArrayNotHasKey('password', $techShow->json('data'));

        Sanctum::actingAs($this->administrator);

        $adminShow = $this->getJson('/api/v1/admin/users/'.$this->employee->id)->assertOk();
        $this->assertIsArray($adminShow->json('data.audit_logs'));
    }

    public function test_an_administrator_can_edit_and_deactivate_a_user(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->patchJson('/api/v1/admin/users/'.$this->employee->id, [
            'job_title' => 'Accounts Clerk',
            'phone' => '0112223333',
        ])->assertOk()->assertJsonPath('data.job_title', 'Accounts Clerk');

        $this->postJson('/api/v1/admin/users/'.$this->employee->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'inactive');

        $this->assertFalse($this->employee->fresh()?->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.deactivated',
            'auditable_id' => $this->employee->id,
        ]);

        $this->postJson('/api/v1/admin/users/'.$this->employee->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active');
    }

    public function test_an_administrator_cannot_deactivate_themselves(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/admin/users/'.$this->administrator->id.'/deactivate')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    public function test_password_reset_returns_a_one_time_code_and_revokes_sessions(): void
    {
        Sanctum::actingAs($this->employee);
        $this->employee->createToken('desk');
        $this->assertSame(1, $this->employee->tokens()->count());

        Sanctum::actingAs($this->administrator);

        $response = $this->postJson('/api/v1/admin/users/'.$this->employee->id.'/reset-password')
            ->assertOk();

        $temporary = $response->json('data.temporary_password');
        $this->assertIsString($temporary);
        $this->assertTrue(Hash::check($temporary, $this->employee->fresh()?->password));
        $this->assertSame(0, $this->employee->tokens()->count());

        $show = $this->getJson('/api/v1/admin/users/'.$this->employee->id)->assertOk();
        $this->assertArrayNotHasKey('temporary_password', $show->json('data'));
        $this->assertStringNotContainsString($temporary, $show->getContent());
    }

    public function test_an_administrator_can_set_a_password_while_updating_a_user(): void
    {
        Sanctum::actingAs($this->employee);
        $this->employee->createToken('desk');

        Sanctum::actingAs($this->administrator);

        $plain = 'Helpdesk@2026!';

        $response = $this->patchJson('/api/v1/admin/users/'.$this->employee->id, [
            'job_title' => 'Front Desk',
            'password' => $plain,
        ])
            ->assertOk()
            ->assertJsonPath('data.job_title', 'Front Desk')
            ->assertJsonPath('data.temporary_password', $plain);

        $this->assertTrue(Hash::check($plain, $this->employee->fresh()?->password));
        $this->assertSame(0, $this->employee->tokens()->count());

        $show = $this->getJson('/api/v1/admin/users/'.$this->employee->id)->assertOk();
        $this->assertArrayNotHasKey('temporary_password', $show->json('data'));
        $this->assertStringNotContainsString($plain, $show->getContent());
    }

    public function test_a_technician_cannot_reset_passwords(): void
    {
        Sanctum::actingAs($this->technician);

        $this->postJson('/api/v1/admin/users/'.$this->employee->id.'/reset-password')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUserPayload(array $overrides = []): array
    {
        $employeeRole = Role::query()->where('slug', RoleSlug::Employee->value)->firstOrFail();

        return array_merge([
            'full_name' => 'Saman Fernando',
            'username' => 's.fernando',
            'employee_id' => 'EMP-4401',
            'epf_number' => 'EPF-4401',
            'email' => 'saman.fernando@example.com',
            'role_id' => $employeeRole->id,
            'branch_id' => $this->employee->branch_id,
        ], $overrides);
    }
}
