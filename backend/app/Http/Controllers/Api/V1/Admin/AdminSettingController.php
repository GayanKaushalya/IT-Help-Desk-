<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSettingsRequest;
use App\Services\SettingService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function show(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('settings.manage')) {
            throw new AuthorizationException;
        }

        return ApiResponse::success($this->settings->all());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->settings->update($request->user(), $request->payload()),
            'System settings have been saved.',
        );
    }
}
