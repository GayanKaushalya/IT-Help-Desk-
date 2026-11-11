<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Liveness endpoint used by the frontend to confirm the API and its database
 * are reachable before the application shell renders.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();

        return ApiResponse::success(
            data: [
                'application' => config('app.name'),
                'environment' => config('app.env'),
                'api_version' => 'v1',
                'database' => $database,
                'server_time' => now()->toIso8601String(),
            ],
            message: $database['connected'] ? 'Service is healthy.' : 'Service is degraded.',
        );
    }

    /**
     * @return array{connected: bool, driver: string, name: string|null}
     */
    private function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $connection->select('SELECT 1');

            return [
                'connected' => true,
                'driver' => $connection->getDriverName(),
                'name' => $connection->getDatabaseName(),
            ];
        } catch (Throwable) {
            // Details are deliberately withheld: connection errors can expose
            // host names and credentials.
            return [
                'connected' => false,
                'driver' => (string) config('database.default'),
                'name' => null,
            ];
        }
    }
}
