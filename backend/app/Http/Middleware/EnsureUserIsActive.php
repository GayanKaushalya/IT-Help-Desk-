<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks authenticated requests from accounts that have been deactivated or
 * are still awaiting approval, so revoking access takes effect immediately
 * rather than when the token eventually expires.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccessSystem()) {
            return ApiResponse::error(
                message: 'This account is inactive or pending approval.',
                status: Response::HTTP_FORBIDDEN,
                code: 'ACCOUNT_INACTIVE',
            );
        }

        return $next($request);
    }
}
