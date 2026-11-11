<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('hr:escalate-overdue')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
        ]);

        // Never trust proxy headers blindly; only the local reverse proxy.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One JSON error shape for the whole API. Messages stay generic so the
        // response never discloses table names, file paths or SQL fragments.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                // Expected, client-facing failures carry their own status and code.
                $e instanceof ApiException => ApiResponse::error(
                    $e->getMessage(),
                    $e->status,
                    code: $e->errorCode,
                ),

                $e instanceof ValidationException => ApiResponse::error(
                    'The submitted data is invalid.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: $e->errors(),
                    code: 'VALIDATION_FAILED',
                ),

                $e instanceof AuthenticationException,
                $e instanceof RouteNotFoundException => ApiResponse::error(
                    'Authentication is required to access this resource.',
                    Response::HTTP_UNAUTHORIZED,
                    code: 'UNAUTHENTICATED',
                ),

                $e instanceof AuthorizationException => ApiResponse::error(
                    'You are not authorized to perform this action.',
                    Response::HTTP_FORBIDDEN,
                    code: 'FORBIDDEN',
                ),

                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'The requested resource was not found.',
                    Response::HTTP_NOT_FOUND,
                    code: 'NOT_FOUND',
                ),

                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getStatusCode() === Response::HTTP_FORBIDDEN
                        ? 'You are not authorized to perform this action.'
                        : ($e->getMessage() ?: 'Request could not be completed.'),
                    $e->getStatusCode(),
                    code: $e->getStatusCode() === Response::HTTP_FORBIDDEN ? 'FORBIDDEN' : 'HTTP_ERROR',
                ),

                default => ApiResponse::error(
                    config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    code: 'SERVER_ERROR',
                ),
            };
        });
    })->create();
