<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleSlug;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            BranchSeeder::class,
            DepartmentSeeder::class,
            TicketPrioritySeeder::class,
            TicketStatusSeeder::class,
            TicketCategorySeeder::class,
        ]);

        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();
    }

    public function test_an_employee_cannot_browse_the_catalogue(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/admin/catalogue')->assertForbidden();
    }

    public function test_an_administrator_can_create_and_update_a_category(): void
    {
        Sanctum::actingAs($this->administrator);

        $created = $this->postJson('/api/v1/admin/catalogue/categories', [
            'name' => 'Video Conferencing',
            'description' => 'Meeting room and Teams issues',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Video Conferencing');

        $id = $created->json('data.id');

        $this->patchJson('/api/v1/admin/catalogue/categories/'.$id, [
            'name' => 'Video Conferencing',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse(TicketCategory::query()->findOrFail($id)->is_active);
    }
}
