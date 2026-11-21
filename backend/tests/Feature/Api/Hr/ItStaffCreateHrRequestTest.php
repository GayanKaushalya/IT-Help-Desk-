<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Hr;

use App\Enums\RoleSlug;
use App\Models\HrCategory;
use App\Models\HrRequestType;
use App\Models\User;
use Database\Seeders\HrCategorySeeder;
use Database\Seeders\HrRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * IT desk operators may also raise personal HR requests as employees.
 */
class ItStaffCreateHrRequestTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/hr/requests';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            HrRequestStatusSeeder::class,
            HrCategorySeeder::class,
        ]);
    }

    public function test_an_it_administrator_can_submit_an_hr_request(): void
    {
        $admin = User::factory()->role(RoleSlug::Administrator)->create();
        [$category, $type] = $this->activeCatalogue();

        Sanctum::actingAs($admin);

        $response = $this->postJson(self::ENDPOINT, [
            'category_id' => $category->id,
            'request_type_id' => $type->id,
            'description' => 'I need a letter confirming my employment for a bank loan.',
            'contact_phone' => '0771234567',
        ])->assertCreated();

        $response->assertJsonPath('data.requester.id', $admin->id);
        $this->assertDatabaseHas('hr_requests', [
            'reference' => $response->json('data.reference'),
            'requester_id' => $admin->id,
            'hr_category_id' => $category->id,
            'hr_request_type_id' => $type->id,
        ]);
    }

    public function test_an_it_technician_can_submit_an_hr_request(): void
    {
        $technician = User::factory()->role(RoleSlug::Technician)->create();
        [$category, $type] = $this->activeCatalogue();

        Sanctum::actingAs($technician);

        $this->postJson(self::ENDPOINT, [
            'category_id' => $category->id,
            'request_type_id' => $type->id,
            'description' => 'Please update my emergency contact details on file.',
            'contact_phone' => '0771234567',
        ])
            ->assertCreated()
            ->assertJsonPath('data.requester.id', $technician->id);
    }

    /**
     * @return array{0: HrCategory, 1: HrRequestType}
     */
    private function activeCatalogue(): array
    {
        $category = HrCategory::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $type = HrRequestType::query()
            ->where('hr_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return [$category, $type];
    }
}
