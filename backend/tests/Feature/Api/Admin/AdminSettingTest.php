<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, BranchSeeder::class, DepartmentSeeder::class]);
        $this->technician = User::factory()->role(RoleSlug::Technician)->create();
        $this->administrator = User::factory()->role(RoleSlug::Administrator)->create();
    }

    public function test_a_technician_cannot_change_settings(): void
    {
        Sanctum::actingAs($this->technician);

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_an_administrator_can_save_settings(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->putJson('/api/v1/admin/settings', [
            'organisation_name' => 'SSI Helpdesk',
            'support_email' => 'it@ssipl.local',
            'support_phone' => '0112345678',
            'support_hours' => 'Weekdays 08:30–17:00',
            'allow_self_registration' => false,
        ])->assertOk()
            ->assertJsonPath('data.organisation_name', 'SSI Helpdesk')
            ->assertJsonPath('data.allow_self_registration', '0');
    }
}
