<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Credential verification and API token lifecycle.
 *
 * Controllers stay free of authentication rules; everything that decides
 * whether a sign-in succeeds, and what the resulting session looks like,
 * lives here.
 */
class AuthService
{
    /**
     * A valid bcrypt hash of a random value, compared against when no matching
     * account exists so that a wrong username and a wrong password take the
     * same amount of time and cannot be told apart.
     */
    private const TIMING_SAFE_HASH = '$2y$12$ifIzhO6KjErLUTmWKtvCM.i1oxM9Q1k8kya9S8lmfmQpY6uxe8.iu';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Verify credentials and issue an API token.
     *
     * @return array{user: User, token: string, expires_at: string|null}
     *
     * @throws InvalidCredentialsException|AccountInactiveException
     */
    public function login(string $username, string $password, bool $remember, Request $request): array
    {
        $user = User::query()
            ->with('role.permissions', 'branch', 'department')
            ->where('username', $username)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            // Still perform a hash comparison for unknown usernames.
            if (! $user) {
                Hash::check($password, self::TIMING_SAFE_HASH);
            }

            $this->audit->log(
                event: 'auth.login_failed',
                description: 'Failed sign-in attempt for username: '.$username,
            );

            throw new InvalidCredentialsException;
        }

        $this->assertCanSignIn($user);

        $session = $this->establishSession($user, $remember, $request);

        $this->audit->log(
            event: 'auth.login',
            subject: $user,
            description: $user->username.' signed in',
        );

        return $session;
    }

    /**
     * Issue an API token for an already-authenticated user.
     *
     * Used after a successful sign-in and immediately after registration so
     * the caller does not have to re-submit the password.
     *
     * @return array{user: User, token: string, expires_at: string|null}
     */
    public function establishSession(User $user, bool $remember, Request $request): array
    {
        $expiresAt = $remember
            ? now()->addDays((int) config('helpdesk.auth.remember_days'))
            : now()->addMinutes((int) config('helpdesk.auth.session_minutes'));

        $token = $user->createToken(
            name: $this->deviceName($request),
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        $this->recordSuccessfulLogin($user, $request);

        $user->loadMissing('role.permissions', 'branch', 'department');

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Revoke only the token used for the current request, leaving any other
     * devices the user is signed in on untouched.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->audit->log(
            event: 'auth.logout',
            subject: $user,
            description: $user->username.' signed out',
        );
    }

    /**
     * @throws AccountInactiveException
     */
    private function assertCanSignIn(User $user): void
    {
        if (! $user->is_approved) {
            $this->audit->log(
                event: 'auth.login_blocked',
                subject: $user,
                description: 'Sign-in blocked: account pending approval',
            );

            throw AccountInactiveException::pendingApproval();
        }

        if (! $user->is_active) {
            $this->audit->log(
                event: 'auth.login_blocked',
                subject: $user,
                description: 'Sign-in blocked: account deactivated',
            );

            throw AccountInactiveException::deactivated();
        }
    }

    private function recordSuccessfulLogin(User $user, Request $request): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();
    }

    /**
     * Human-readable token label so sessions can be identified in the admin UI.
     */
    private function deviceName(Request $request): string
    {
        return mb_substr($request->userAgent() ?: 'Unknown device', 0, 120);
    }
}
