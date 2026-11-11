<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/register';

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $this->branch = Branch::factory()->create(['name' => 'Head Office']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Nimal Perera',
            'branch_id' => $this->branch->id,
            'employee_id' => 'EMP-4401',
            'username' => 'n.perera',
            'password' => UserFactory::PASSWORD,
            'password_confirmation' => UserFactory::PASSWORD,
        ], $overrides);
    }

    public function test_a_valid_registration_creates_an_employee_and_returns_a_token(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'username' => 'n.perera',
                        'full_name' => 'Nimal Perera',
                        'employee_id' => 'EMP-4401',
                        'epf_number' => 'NA-EMP-4401',
                        'is_active' => true,
                        'role' => ['slug' => RoleSlug::Employee->value],
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertStringNotContainsString('password', mb_strtolower($response->getContent()));

        $user = User::where('username', 'n.perera')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_approved);
        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $user->password));
        $this->assertSame(RoleSlug::Employee->value, $user->role->slug);
        $this->assertTrue(
            AuditLog::query()->where('event', 'auth.register')->where('auditable_id', $user->id)->exists()
        );
    }

    public function test_a_duplicate_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'n.perera']);

        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.username.0', 'This username is already taken.');
    }

    public function test_a_duplicate_employee_id_is_rejected(): void
    {
        User::factory()->create(['employee_id' => 'EMP-4401']);

        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.employee_id.0', 'This employee ID is already registered.');
    }

    public function test_registration_does_not_require_an_epf_number(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.user.epf_number', 'NA-EMP-4401');

        $this->assertDatabaseHas('users', [
            'username' => 'n.perera',
            'epf_number' => 'NA-EMP-4401',
        ]);
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload([
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_a_password_mismatch_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload([
            'password_confirmation' => 'Different@123',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password confirmation does not match.');
    }

    public function test_a_missing_branch_is_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['branch_id']);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.branch_id.0', 'Select your branch.');
    }

    public function test_an_inactive_branch_cannot_be_used(): void
    {
        $inactive = Branch::factory()->inactive()->create();

        $this->postJson(self::ENDPOINT, $this->validPayload(['branch_id' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.branch_id.0', 'The selected branch is not available.');
    }

    public function test_an_unknown_branch_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload(['branch_id' => 9999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_submitted_role_fields_cannot_escalate_privileges(): void
    {
        $admin = Role::where('slug', RoleSlug::SuperAdmin->value)->first();

        $response = $this->postJson(self::ENDPOINT, $this->validPayload([
            'role_id' => $admin->id,
            'role' => 'super-admin',
            'is_admin' => true,
            'is_approved' => false,
            'is_active' => false,
        ]))->assertCreated();

        $this->assertSame(RoleSlug::Employee->value, $response->json('data.user.role.slug'));

        $user = User::where('username', 'n.perera')->first();

        $this->assertNotSame($admin->id, $user->role_id);
        $this->assertSame(RoleSlug::Employee->value, $user->role->slug);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_approved);
    }

    public function test_the_new_account_can_reach_authenticated_endpoints(): void
    {
        $token = $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertCreated()
            ->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'n.perera');
    }
}
