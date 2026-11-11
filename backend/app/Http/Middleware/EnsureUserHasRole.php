<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for role-gated areas, e.g. `role:administrator,super-admin`.
 *
 * This is the coarse gate that keeps employees out of the admin API surface;
 * finer-grained checks belong in policies.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            return ApiResponse::error(
                message: 'You do not have permission to access this resource.',
                status: Response::HTTP_FORBIDDEN,
                code: 'ROLE_REQUIRED',
            );
        }

        return $next($request);
    }
}
