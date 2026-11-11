<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for a specific ability, e.g. `permission:tickets.assign`.
 *
 * All listed permissions must be held; use separate routes when an either/or
 * rule is required.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if (! $user || ! $user->hasPermission($permission)) {
                return ApiResponse::error(
                    message: 'You do not have permission to perform this action.',
                    status: Response::HTTP_FORBIDDEN,
                    code: 'PERMISSION_REQUIRED',
                );
            }
        }

        return $next($request);
    }
}
