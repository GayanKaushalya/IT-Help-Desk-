<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_a_healthy_service(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'api_version' => 'v1',
                    'database' => ['connected' => true],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['application', 'environment', 'api_version', 'database', 'server_time'],
            ]);
    }

    public function test_unknown_api_routes_return_the_standard_error_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'code' => 'NOT_FOUND',
            ]);
    }
}
