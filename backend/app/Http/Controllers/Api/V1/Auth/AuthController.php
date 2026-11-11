<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly RegistrationService $registration,
    ) {}

    /**
     * Exchange credentials for an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        ['username' => $username, 'password' => $password, 'remember' => $remember] =
            $request->credentials();

        $session = $this->auth->login($username, $password, $remember, $request);

        return ApiResponse::success(
            data: [
                'user' => new UserResource($session['user']),
                'token' => $session['token'],
                'token_type' => 'Bearer',
                'expires_at' => $session['expires_at'],
            ],
            message: 'Signed in successfully.',
        );
    }

    /**
     * Create an employee account and return a session token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $session = $this->registration->register($request->registrationData(), $request);

        return ApiResponse::created(
            data: [
                'user' => new UserResource($session['user']),
                'token' => $session['token'],
                'token_type' => 'Bearer',
                'expires_at' => $session['expires_at'],
            ],
            message: 'Account created successfully.',
        );
    }

    /**
     * The signed-in user, used by the SPA to restore a session on page load.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('role.permissions', 'branch', 'department');

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Revoke the token backing the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->auth->logout($user);

        return ApiResponse::success(message: 'Signed out successfully.');
    }
}
