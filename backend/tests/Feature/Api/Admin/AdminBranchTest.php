<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBranchTest extends TestCase
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
            BranchSeeder::class,
            DepartmentSeeder::class,
        ]);

        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();
    }

    public function test_an_employee_cannot_manage_branches(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/admin/branches')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_a_technician_cannot_manage_branches(): void
    {
        Sanctum::actingAs($this->technician);

        $this->getJson('/api/v1/admin/branches')->assertForbidden();
        $this->postJson('/api/v1/admin/branches', [
            'name' => 'Negombo Branch',
            'code' => 'BR-NEG',
        ])->assertForbidden();
    }

    public function test_an_administrator_can_create_and_edit_a_branch(): void
    {
        Sanctum::actingAs($this->administrator);

        $created = $this->postJson('/api/v1/admin/branches', [
            'name' => 'Negombo Branch',
            'code' => 'br-neg',
            'address' => '12 Harbour Road',
            'phone' => '0312221111',
            'city' => 'Negombo',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'BR-NEG')
            ->assertJsonPath('data.name', 'Negombo Branch')
            ->assertJsonPath('data.address', '12 Harbour Road')
            ->assertJsonPath('data.phone', '0312221111')
            ->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');

        $this->patchJson('/api/v1/admin/branches/'.$id, [
            'address' => '14 Harbour Road',
        ])->assertOk()->assertJsonPath('data.address', '14 Harbour Road');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'branch.created',
            'auditable_id' => $id,
        ]);
    }

    public function test_duplicate_branch_codes_are_rejected(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/admin/branches', [
            'name' => 'Another Head Office',
            'code' => 'ITPL-CMB',
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_deactivating_a_branch_removes_it_from_registration_lookups(): void
    {
        $branch = Branch::query()->where('code', 'ITPL-KAN')->firstOrFail();

        Sanctum::actingAs($this->administrator);

        $this->postJson('/api/v1/admin/branches/'.$branch->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $public = $this->getJson('/api/v1/lookups/public')->assertOk();
        $codes = collect($public->json('data.branches'))->pluck('code')->all();

        $this->assertNotContains('ITPL-KAN', $codes);
        $this->assertContains('ITPL-CMB', $codes);

        $this->postJson('/api/v1/admin/branches/'.$branch->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $restored = $this->getJson('/api/v1/lookups/public')->assertOk();
        $this->assertContains('ITPL-KAN', collect($restored->json('data.branches'))->pluck('code')->all());
    }

    public function test_branch_search_and_status_filters_work(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->getJson('/api/v1/admin/branches?search=negombo')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'ITPL-NEG');

        Branch::query()->where('code', 'IMS-HYD')->update(['is_active' => false]);

        $this->getJson('/api/v1/admin/branches?status=inactive')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'IMS-HYD');
    }
}
