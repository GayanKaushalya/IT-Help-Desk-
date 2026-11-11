<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/auth/login';

    public function test_valid_credentials_return_a_token_and_the_user(): void
    {
        $user = User::factory()->create(['username' => 'n.perera']);

        $response = $this->postJson(self::ENDPOINT, [
            'username' => 'n.perera',
            'password' => UserFactory::PASSWORD,
        ])->assertOk();

        $response->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'user' => ['username' => 'n.perera'],
            ],
        ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.expires_at'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_the_response_never_exposes_password_fields(): void
    {
        User::factory()->create(['username' => 'k.silva']);

        $response = $this->postJson(self::ENDPOINT, [
            'username' => 'k.silva',
            'password' => UserFactory::PASSWORD,
        ])->assertOk();

        $user = $response->json('data.user');

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('remember_token', $user);
        $this->assertStringNotContainsString('password', mb_strtolower($response->getContent()));
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'a.fernando']);

        $this->postJson(self::ENDPOINT, [
            'username' => 'a.fernando',
            'password' => 'not-the-password',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'The username or password is incorrect.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_unknown_username_gives_the_same_answer_as_a_wrong_password(): void
    {
        User::factory()->create(['username' => 'a.fernando']);

        $unknown = $this->postJson(self::ENDPOINT, [
            'username' => 'nobody',
            'password' => 'not-the-password',
        ])->assertUnauthorized();

        $wrongPassword = $this->postJson(self::ENDPOINT, [
            'username' => 'a.fernando',
            'password' => 'not-the-password',
        ])->assertUnauthorized();

        $this->assertSame($unknown->json('message'), $wrongPassword->json('message'));
        $this->assertSame($unknown->json('code'), $wrongPassword->json('code'));
    }

    public function test_failed_attempts_are_recorded_in_the_audit_trail(): void
    {
        $this->postJson(self::ENDPOINT, ['username' => 'ghost', 'password' => 'whatever'])
            ->assertUnauthorized();

        $this->assertTrue(
            AuditLog::query()->where('event', 'auth.login_failed')->exists()
        );
    }

    public function test_an_account_awaiting_approval_cannot_sign_in(): void
    {
        User::factory()->pendingApproval()->create(['username' => 'pending.user']);

        $this->postJson(self::ENDPOINT, [
            'username' => 'pending.user',
            'password' => UserFactory::PASSWORD,
        ])
            ->assertForbidden()
            ->assertJson(['code' => 'ACCOUNT_PENDING_APPROVAL']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        User::factory()->deactivated()->create(['username' => 'former.staff']);

        $this->postJson(self::ENDPOINT, [
            'username' => 'former.staff',
            'password' => UserFactory::PASSWORD,
        ])
            ->assertForbidden()
            ->assertJson(['code' => 'ACCOUNT_DEACTIVATED']);
    }

    public function test_missing_fields_are_rejected_with_validation_errors(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertUnprocessable()
            ->assertJson(['code' => 'VALIDATION_FAILED'])
            ->assertJsonStructure(['errors' => ['username', 'password']]);
    }

    public function test_repeated_attempts_are_rate_limited(): void
    {
        User::factory()->create(['username' => 'target.user']);

        $payload = ['username' => 'target.user', 'password' => 'wrong'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(self::ENDPOINT, $payload)->assertUnauthorized();
        }

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(429);
    }

    public function test_a_super_admin_receives_their_role_and_permissions(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        User::factory()->role(RoleSlug::SuperAdmin)->create(['username' => 'superadmin']);

        $response = $this->postJson(self::ENDPOINT, [
            'username' => 'superadmin',
            'password' => UserFactory::PASSWORD,
        ])->assertOk();

        $this->assertSame('super-admin', $response->json('data.user.role.slug'));
        $this->assertContains('settings.manage', $response->json('data.user.role.permissions'));
    }
}
