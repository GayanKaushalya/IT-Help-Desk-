<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('role.permissions', 'branch', 'department');

        return ApiResponse::success((new UserResource($user))->resolve());
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            (new UserResource($this->profiles->update($user, $request->payload())))->resolve(),
            'Your profile has been updated.',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $payload = $request->payload();

        $updated = $this->profiles->changePassword($user, $payload['current_password'], $payload['password']);

        $current = $updated->currentAccessToken();
        if ($current instanceof PersonalAccessToken) {
            $updated->tokens()->where('id', '!=', $current->id)->delete();
        }

        return ApiResponse::success(
            (new UserResource($updated))->resolve(),
            'Your password has been changed. Other sessions have been signed out.',
        );
    }
}
