<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserNotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $module = $this->module($request);

        return ApiResponse::success([
            'unread_count' => $this->notifications->unreadCount($user, $module),
            'notifications' => UserNotificationResource::collection(
                $this->notifications->recent($user, NotificationService::RECENT_LIMIT, $module)
            )->resolve(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'unread_count' => $this->notifications->unreadCount($user, $this->module($request)),
        ]);
    }

    public function markRead(Request $request, UserNotification $user_notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->notifications->markRead($user, $user_notification);

        return ApiResponse::success(
            (new UserNotificationResource($updated))->resolve(),
            'Notification marked as read.',
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->notifications->markAllRead($user, $this->module($request));

        return ApiResponse::success(
            ['updated' => $updated],
            $updated > 0 ? 'All notifications marked as read.' : 'There are no unread notifications.',
        );
    }

    /**
     * @return 'it'|'hr'|'administration'|null
     */
    private function module(Request $request): ?string
    {
        $module = $request->query('module');

        return in_array($module, ['it', 'hr', 'administration'], true) ? $module : null;
    }
}
