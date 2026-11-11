<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_lookups_are_available_without_a_session(): void
    {
        $this->seed([BranchSeeder::class, DepartmentSeeder::class]);

        $response = $this->getJson('/api/v1/lookups/public')->assertOk();

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.branches'));
        $this->assertNotEmpty($response->json('data.departments'));
    }

    public function test_the_full_catalogue_requires_authentication(): void
    {
        $this->getJson('/api/v1/lookups')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
            ]);
    }
}
