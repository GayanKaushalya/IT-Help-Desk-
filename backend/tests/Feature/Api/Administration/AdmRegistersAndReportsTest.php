<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Administration;

use App\Enums\RoleSlug;
use App\Models\AdmCategory;
use App\Models\FuelCard;
use App\Models\InsurancePolicy;
use App\Models\User;
use Database\Seeders\AdmCategorySeeder;
use Database\Seeders\AdmRequestStatusSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmRegistersAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/administration/staff';

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, AdmRequestStatusSeeder::class, AdmCategorySeeder::class]);

        $this->manager = User::factory()->role(RoleSlug::AdministrationManager)->create();
        Sanctum::actingAs($this->manager);
    }

    public function test_vehicles_and_drivers_crud(): void
    {
        $driverId = $this->postJson(self::BASE.'/registers/drivers', [
            'full_name' => 'Kamal Silva',
            'license_number' => 'B1234567',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'phone' => '0771234567',
        ])->assertCreated()->assertJsonPath('data.status', 'active')->json('data.id');

        $vehicleId = $this->postJson(self::BASE.'/registers/vehicles', [
            'registration_number' => 'cab-9999',
            'make' => 'Toyota',
            'model' => 'Hiace',
            'company_name' => 'Acme Lanka',
            'driver_id' => $driverId,
        ])->assertCreated()
            ->assertJsonPath('data.registration_number', 'CAB-9999')
            ->assertJsonPath('data.driver.id', $driverId)
            ->json('data.id');

        $this->postJson(self::BASE.'/registers/vehicles', ['registration_number' => 'CAB-9999'])
            ->assertUnprocessable()->assertJsonValidationErrors(['registration_number']);

        $this->patchJson(self::BASE.'/registers/vehicles/'.$vehicleId, ['status' => 'maintenance'])
            ->assertOk()->assertJsonPath('data.status', 'maintenance');

        $this->getJson(self::BASE.'/registers/vehicles?status=maintenance&search=hiace')
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->patchJson(self::BASE.'/registers/drivers/'.$driverId, ['status' => 'on_leave'])->assertOk();
        $this->getJson(self::BASE.'/registers/drivers?status=active')->assertOk()->assertJsonPath('meta.total', 0);

        $this->deleteJson(self::BASE.'/registers/vehicles/'.$vehicleId)->assertOk();
        $this->deleteJson(self::BASE.'/registers/drivers/'.$driverId)->assertOk();
        $this->assertSoftDeleted('vehicles', ['id' => $vehicleId]);
    }

    public function test_vehicle_with_upcoming_allocation_cannot_be_deleted(): void
    {
        $vehicleId = $this->postJson(self::BASE.'/registers/vehicles', ['registration_number' => 'CAB-1'])
            ->assertCreated()->json('data.id');

        $this->postJson(self::BASE.'/registers/allocations', [
            'vehicle_id' => $vehicleId,
            'start_at' => now()->addDay()->format('Y-m-d H:i'),
            'end_at' => now()->addDays(2)->format('Y-m-d H:i'),
        ])->assertCreated();

        $this->deleteJson(self::BASE.'/registers/vehicles/'.$vehicleId)
            ->assertUnprocessable()->assertJsonValidationErrors(['vehicle']);
    }

    public function test_fuel_cards_store_only_a_masked_reference_and_never_a_pin(): void
    {
        $vehicleId = $this->postJson(self::BASE.'/registers/vehicles', ['registration_number' => 'CAB-5'])
            ->assertCreated()->json('data.id');

        $response = $this->postJson(self::BASE.'/registers/fuel-cards', [
            'card_number' => 'FUEL-0001',
            'card_ref' => '1234 5678 9012 3456',
            'provider' => 'Ceypetco',
            'vehicle_id' => $vehicleId,
            'assigned_user_id' => $this->manager->id,
            'monthly_limit' => 50000,
        ])->assertCreated()
            ->assertJsonPath('data.masked_card_ref', '**** **** **** 3456')
            ->assertJsonPath('data.vehicle.registration_number', 'CAB-5');

        $id = $response->json('data.id');
        $raw = json_encode(FuelCard::query()->findOrFail($id)->getAttributes());
        $this->assertStringNotContainsString('1234 5678', (string) $raw);
        $this->assertStringNotContainsString('9012', (string) $raw);

        // PINs / CVVs are rejected outright.
        $this->postJson(self::BASE.'/registers/fuel-cards', ['card_number' => 'FUEL-0002', 'pin' => '4321'])
            ->assertUnprocessable()->assertJsonValidationErrors(['pin']);

        // A full PAN is not an acceptable internal id.
        $this->postJson(self::BASE.'/registers/fuel-cards', ['card_number' => '4111 1111 1111 1111'])
            ->assertUnprocessable()->assertJsonValidationErrors(['card_number']);

        $this->patchJson(self::BASE.'/registers/fuel-cards/'.$id, ['status' => 'lost'])->assertOk();

        $this->getJson(self::BASE.'/registers/fuel-cards/'.$id.'/history')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'status_changed');

        $this->getJson(self::BASE.'/registers/fuel-cards?search=3456')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_sla_page_lists_and_updates_category_hours(): void
    {
        $category = AdmCategory::query()->where('slug', 'courier-document-dispatch')->firstOrFail();

        $this->getJson(self::BASE.'/sla')->assertOk()->assertJsonCount(AdmCategory::query()->count(), 'data');

        $this->patchJson(self::BASE.'/sla/categories/'.$category->id, ['target_hours' => 12, 'apply_to_types' => true])
            ->assertOk();

        $this->assertDatabaseHas('adm_categories', ['id' => $category->id, 'default_sla_hours' => 12]);
        $this->assertDatabaseMissing('adm_request_types', ['adm_category_id' => $category->id, 'sla_hours' => 24]);

        $this->patchJson(self::BASE.'/sla/categories/'.$category->id, ['target_hours' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['target_hours']);

        // Officers (no adm.sla.manage) are blocked.
        Sanctum::actingAs(User::factory()->role(RoleSlug::AdministrationOfficer)->create());
        $this->getJson(self::BASE.'/sla')->assertForbidden();
    }

    public function test_reports_return_live_aggregates(): void
    {
        InsurancePolicy::query()->create([
            'policy_number' => 'P-EXP', 'provider' => 'X', 'insurance_type' => 'Motor',
            'expiry_date' => now()->subDays(3)->toDateString(),
        ]);
        InsurancePolicy::query()->create([
            'policy_number' => 'P-SOON', 'provider' => 'X', 'insurance_type' => 'Motor',
            'expiry_date' => now()->addDays(15)->toDateString(),
        ]);

        $employee = User::factory()->role(RoleSlug::Employee)->create();
        Sanctum::actingAs($employee);
        $category = AdmCategory::query()->where('slug', 'general-administration')->firstOrFail();
        $this->postJson('/api/v1/administration/requests', [
            'category_id' => $category->id,
            'request_type_id' => $category->requestTypes()->value('id'),
            'subject' => 'Report test request',
            'description' => 'A request that should be counted in reports.',
        ])->assertCreated();

        Sanctum::actingAs($this->manager);

        $this->getJson(self::BASE.'/reports')
            ->assertOk()
            ->assertJsonPath('data.kpis.total_requests', 1)
            ->assertJsonPath('data.kpis.open_requests', 1)
            ->assertJsonPath('data.insurance.expired', 1)
            ->assertJsonPath('data.insurance.expiring_30_days', 1)
            ->assertJsonPath('data.registers.insurance_policies', 2)
            ->assertJsonCount(6, 'data.monthly_trend');

        $this->getJson(self::BASE.'/reports?date_from='.now()->addDays(5)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.kpis.total_requests', 0);

        Sanctum::actingAs(User::factory()->role(RoleSlug::InsuranceOfficer)->create());
        $this->getJson(self::BASE.'/reports')->assertOk(); // adm.reports.view is part of the desk set

        Sanctum::actingAs($employee);
        $this->getJson(self::BASE.'/reports')->assertForbidden();
    }
}
