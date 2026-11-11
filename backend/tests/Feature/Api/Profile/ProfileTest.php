<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Profile;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, BranchSeeder::class, DepartmentSeeder::class]);
        $this->employee = User::factory()->role(RoleSlug::Employee)->create();
    }

    public function test_an_employee_can_update_contact_details(): void
    {
        Sanctum::actingAs($this->employee);

        $this->patchJson('/api/v1/profile', [
            'email' => 'nimal.updated@example.com',
            'phone' => '0771234567',
            'job_title' => 'Accounts Clerk',
        ])->assertOk()
            ->assertJsonPath('data.email', 'nimal.updated@example.com')
            ->assertJsonPath('data.phone', '0771234567')
            ->assertJsonPath('data.job_title', 'Accounts Clerk');
    }

    public function test_an_employee_can_change_their_password(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/profile/password', [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'NewPass@2026!',
            'password_confirmation' => 'NewPass@2026!',
        ])->assertOk();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NewPass@2026!', $this->employee->fresh()->password)
        );
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'WrongPass@1',
            'password' => 'NewPass@2026!',
            'password_confirmation' => 'NewPass@2026!',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_CURRENT_PASSWORD');
    }
}
