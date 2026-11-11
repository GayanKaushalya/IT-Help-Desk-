<?php

declare(strict_types=1);

namespace App\Providers;

use App\Database\SqlServerLegacyConnection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Routes every sqlsrv connection through the legacy-aware connection so
        // the pre-2012 pagination grammar can be enabled per connection.
        Connection::resolverFor(
            'sqlsrv',
            fn ($pdo, $database, $prefix, $config) => new SqlServerLegacyConnection($pdo, $database, $prefix, $config)
        );
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configurePasswordPolicy();
        $this->configureRateLimiting();
    }

    /**
     * Fail loudly on lazy loading and unset attributes outside production, so
     * N+1 problems surface during development instead of in front of users.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    private function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Named limiters referenced by the route groups in routes/api.php.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Credential endpoints are throttled by identity as well as IP so that a
        // single account cannot be brute forced from rotating addresses.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth-user:'.mb_strtolower((string) $request->input('username'))),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(8)
            ->by('register-ip:'.$request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('verify', fn (Request $request) => Limit::perMinute(10)
            ->by('verify:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));
    }
}
