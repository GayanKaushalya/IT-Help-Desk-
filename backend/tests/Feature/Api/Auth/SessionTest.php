<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    /** Signs in through the real endpoint so the token is a genuine one. */
    private function signIn(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => UserFactory::PASSWORD,
        ])->assertOk()->json('data.token');
    }

    /**
     * Laravel keeps one application instance for a whole test method, and
     * RequestGuard memoises the user it resolved. Dropping the guards makes the
     * next call re-read the token the way a genuine new request would.
     */
    private function startFreshRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_the_current_user_can_be_retrieved_with_a_token(): void
    {
        $user = User::factory()->create(['username' => 'r.jayasuriya']);
        $token = $this->signIn($user);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'username' => 'r.jayasuriya',
                    'role' => ['slug' => 'employee'],
                ],
            ]);
    }

    public function test_the_current_user_cannot_be_retrieved_without_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->withToken('1|completely-made-up-token')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();

        $firstDevice = $this->signIn($user);
        $secondDevice = $this->signIn($user);

        $this->withToken($firstDevice)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->startFreshRequest();
        $this->withToken($firstDevice)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->startFreshRequest();
        $this->withToken($secondDevice)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->signIn($user);

        PersonalAccessToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_a_session_is_refused_once_the_account_is_deactivated(): void
    {
        $user = User::factory()->create();
        $token = $this->signIn($user);

        $user->forceFill(['is_active' => false])->saveQuietly();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJson(['code' => 'ACCOUNT_INACTIVE']);
    }

    public function test_a_deactivated_account_can_still_sign_out(): void
    {
        $user = User::factory()->create();
        $token = $this->signIn($user);

        $user->forceFill(['is_active' => false])->saveQuietly();
        $this->startFreshRequest();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_remember_me_issues_a_longer_lived_token(): void
    {
        $user = User::factory()->create(['username' => 'long.session']);

        $short = $this->postJson('/api/v1/auth/login', [
            'username' => 'long.session',
            'password' => UserFactory::PASSWORD,
        ])->json('data.expires_at');

        $long = $this->postJson('/api/v1/auth/login', [
            'username' => 'long.session',
            'password' => UserFactory::PASSWORD,
            'remember' => true,
        ])->json('data.expires_at');

        $this->assertTrue(now()->parse($long)->greaterThan(now()->parse($short)));
    }
}
