<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises the authorization middleware itself.
 *
 * Routes are registered here rather than relying on a feature endpoint, so the
 * guarantee is proven independently of any module that happens to use it.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Route::middleware(['api', 'auth:sanctum', 'active', 'role:administrator,super-admin'])
            ->get('/test/admin-area', fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'active', 'role:'.implode(',', RoleSlug::staffSlugs())])
            ->get('/test/staff-area', fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'active', 'permission:tickets.assign'])
            ->get('/test/assign', fn () => response()->json(['ok' => true]));
    }

    public function test_an_employee_cannot_reach_the_admin_area(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Employee)->create());

        $this->getJson('/test/admin-area')
            ->assertForbidden()
            ->assertJson(['code' => 'ROLE_REQUIRED']);
    }

    public function test_an_employee_cannot_reach_the_staff_area(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Employee)->create());

        $this->getJson('/test/staff-area')->assertForbidden();
    }

    public function test_a_technician_reaches_the_staff_area_but_not_the_admin_area(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Technician)->create());

        $this->getJson('/test/staff-area')->assertOk();
        $this->getJson('/test/admin-area')->assertForbidden();
    }

    public function test_an_administrator_reaches_the_admin_area(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Administrator)->create());

        $this->getJson('/test/admin-area')->assertOk();
    }

    public function test_a_super_admin_reaches_every_area(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::SuperAdmin)->create());

        $this->getJson('/test/staff-area')->assertOk();
        $this->getJson('/test/admin-area')->assertOk();
    }

    public function test_guarded_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/test/admin-area')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_permissions_are_enforced_independently_of_role_names(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleSlug::Technician)->create());
        $this->getJson('/test/assign')
            ->assertForbidden()
            ->assertJson(['code' => 'PERMISSION_REQUIRED']);

        Sanctum::actingAs(User::factory()->role(RoleSlug::Supervisor)->create());
        $this->getJson('/test/assign')->assertOk();
    }

    public function test_a_deactivated_account_is_refused_even_with_the_right_role(): void
    {
        Sanctum::actingAs(
            User::factory()->role(RoleSlug::Administrator)->deactivated()->create()
        );

        $this->getJson('/test/admin-area')
            ->assertForbidden()
            ->assertJson(['code' => 'ACCOUNT_INACTIVE']);
    }
}
