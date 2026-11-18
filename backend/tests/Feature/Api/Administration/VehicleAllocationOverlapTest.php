<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Administration;

use App\Enums\RoleSlug;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleAllocationOverlapTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/administration/staff/registers/allocations';

    private User $fleet;

    private Vehicle $vehicle;

    private Vehicle $otherVehicle;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->fleet = User::factory()->role(RoleSlug::FleetOfficer)->create();
        $this->vehicle = Vehicle::query()->create(['registration_number' => 'CAB-1111', 'status' => 'available']);
        $this->otherVehicle = Vehicle::query()->create(['registration_number' => 'CAB-2222', 'status' => 'available']);
        $this->driver = Driver::query()->create([
            'full_name' => 'Nimal Perera',
            'status' => 'active',
            'license_expiry_date' => now()->addYears(10)->toDateString(),
        ]);

        Sanctum::actingAs($this->fleet);
    }

    /**
     * @return array<string, mixed>
     */
    private function slot(string $start, string $end, array $extra = []): array
    {
        return array_merge([
            'vehicle_id' => $this->vehicle->id,
            'start_at' => $start,
            'end_at' => $end,
            'purpose' => 'Site visit',
        ], $extra);
    }

    public function test_overlapping_allocation_for_the_same_vehicle_is_rejected(): void
    {
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 17:00'))->assertCreated();

        foreach ([
            ['2030-03-10 08:00', '2030-03-10 10:00'], // overlaps start
            ['2030-03-10 16:00', '2030-03-10 18:00'], // overlaps end
            ['2030-03-10 10:00', '2030-03-10 12:00'], // inside
            ['2030-03-10 07:00', '2030-03-10 20:00'], // encloses
        ] as [$start, $end]) {
            $this->postJson(self::BASE, $this->slot($start, $end))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['vehicle_id']);
        }

        $this->assertSame(1, VehicleAllocation::query()->count());
    }

    public function test_back_to_back_and_different_vehicle_bookings_are_allowed(): void
    {
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00'))->assertCreated();

        $this->postJson(self::BASE, $this->slot('2030-03-10 12:00', '2030-03-10 15:00'))->assertCreated();
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00', ['vehicle_id' => $this->otherVehicle->id]))
            ->assertCreated();
    }

    public function test_overlapping_driver_booking_is_rejected_even_for_another_vehicle(): void
    {
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00', ['driver_id' => $this->driver->id]))
            ->assertCreated();

        $this->postJson(self::BASE, $this->slot('2030-03-10 11:00', '2030-03-10 14:00', [
            'vehicle_id' => $this->otherVehicle->id,
            'driver_id' => $this->driver->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['driver_id']);
    }

    public function test_cancelled_allocations_do_not_block_the_slot(): void
    {
        $id = $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00'))
            ->assertCreated()->json('data.id');

        $this->postJson(self::BASE.'/'.$id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->postJson(self::BASE, $this->slot('2030-03-10 10:00', '2030-03-10 11:00'))->assertCreated();

        // A cancelled booking cannot be revived.
        $this->patchJson(self::BASE.'/'.$id, ['purpose' => 'x'])->assertUnprocessable();
    }

    public function test_updating_checks_overlap_but_ignores_the_allocation_itself(): void
    {
        $first = $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00'))->assertCreated()->json('data.id');
        $this->postJson(self::BASE, $this->slot('2030-03-10 13:00', '2030-03-10 15:00'))->assertCreated();

        // Extending within free time (overlaps only itself) is fine.
        $this->patchJson(self::BASE.'/'.$first, ['end_at' => '2030-03-10 12:30'])->assertOk();

        // Extending into the next booking is not.
        $this->patchJson(self::BASE.'/'.$first, ['end_at' => '2030-03-10 14:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_unavailable_vehicle_inactive_driver_and_expired_licence_are_rejected(): void
    {
        $this->vehicle->update(['status' => 'maintenance']);
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00'))
            ->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);

        $this->vehicle->update(['status' => 'available']);

        $this->driver->update(['status' => 'on_leave']);
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00', ['driver_id' => $this->driver->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['driver_id']);

        $this->driver->update(['status' => 'active', 'license_expiry_date' => '2030-03-01']);
        $this->postJson(self::BASE, $this->slot('2030-03-10 09:00', '2030-03-10 12:00', ['driver_id' => $this->driver->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['driver_id']);
    }

    public function test_end_must_be_after_start(): void
    {
        $this->postJson(self::BASE, $this->slot('2030-03-10 12:00', '2030-03-10 09:00'))
            ->assertUnprocessable()->assertJsonValidationErrors(['end_at']);
    }
}
