<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books vehicles (and optionally drivers) for a date/time window.
 *
 * Every write runs in a transaction that first locks the vehicle (and driver)
 * rows, so two concurrent bookings for the same resource are serialised and the
 * overlap check cannot be raced.
 */
class VehicleAllocationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, VehicleAllocation>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = VehicleAllocation::query()->with(['vehicle', 'driver', 'admRequest', 'creator']);

        foreach (['vehicle_id', 'driver_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, (int) $filters[$column]);
            }
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        // Windows that intersect the requested range.
        if (filled($filters['date_from'] ?? null)) {
            $query->where('end_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('start_at', '<=', $filters['date_to'].' 23:59:59');
        }

        return $query
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->paginate(RegisterPagination::perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): VehicleAllocation
    {
        $allocation = DB::transaction(function () use ($actor, $data): VehicleAllocation {
            $attributes = $this->normalise($data) + ['status' => 'scheduled'];

            $this->guard($attributes, null);

            return VehicleAllocation::query()->create($attributes + ['created_by' => $actor->id]);
        });

        $this->audit->log(
            event: 'vehicle_allocation.created',
            subject: $allocation,
            newValues: $allocation->only(['vehicle_id', 'driver_id', 'start_at', 'end_at']),
            description: $actor->username.' allocated vehicle #'.$allocation->vehicle_id,
        );

        return $allocation->load(['vehicle', 'driver', 'admRequest', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(VehicleAllocation $allocation, User $actor, array $data): VehicleAllocation
    {
        DB::transaction(function () use ($allocation, $data): void {
            // Re-read under a lock so a concurrent cancel/update is visible.
            $current = VehicleAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if ($current->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => ['A cancelled allocation can no longer be changed. Create a new allocation instead.'],
                ]);
            }

            $attributes = $this->normalise($data);
            $merged = array_merge($current->only(['vehicle_id', 'driver_id', 'start_at', 'end_at', 'status']), $attributes);

            if (($merged['status'] ?? $current->status) !== 'cancelled') {
                $this->guard($merged, $current->id);
            }

            $current->fill($attributes)->save();
            $allocation->setRawAttributes($current->getAttributes(), true);
        });

        $this->audit->log(
            event: 'vehicle_allocation.updated',
            subject: $allocation,
            newValues: $allocation->only(['vehicle_id', 'driver_id', 'start_at', 'end_at', 'status']),
            description: $actor->username.' updated allocation #'.$allocation->id,
        );

        return $allocation->load(['vehicle', 'driver', 'admRequest', 'creator']);
    }

    public function cancel(VehicleAllocation $allocation, User $actor): VehicleAllocation
    {
        DB::transaction(function () use ($allocation): void {
            $current = VehicleAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $current->forceFill(['status' => 'cancelled'])->save();
            $allocation->setRawAttributes($current->getAttributes(), true);
        });

        $this->audit->log(
            event: 'vehicle_allocation.cancelled',
            subject: $allocation,
            description: $actor->username.' cancelled allocation #'.$allocation->id,
        );

        return $allocation->load(['vehicle', 'driver', 'admRequest', 'creator']);
    }

    /**
     * Must run inside a transaction. Throws a 422 naming the clashing booking.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function guard(array $attributes, ?int $ignoreId): void
    {
        $start = Carbon::parse($attributes['start_at']);
        $end = Carbon::parse($attributes['end_at']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['end_at' => ['The end must be after the start.']]);
        }

        $vehicle = Vehicle::query()->whereKey($attributes['vehicle_id'])->lockForUpdate()->first();

        if (! $vehicle) {
            throw ValidationException::withMessages(['vehicle_id' => ['The selected vehicle is not available.']]);
        }

        if ($vehicle->status !== 'available') {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Vehicle '.$vehicle->registration_number.' is '.$vehicle->status.' and cannot be allocated.'],
            ]);
        }

        $this->assertFree('vehicle_id', 'Vehicle '.$vehicle->registration_number, (int) $vehicle->id, $start, $end, $ignoreId);

        $driverId = $attributes['driver_id'] ?? null;

        if ($driverId === null) {
            return;
        }

        $driver = Driver::query()->whereKey($driverId)->lockForUpdate()->first();

        if (! $driver) {
            throw ValidationException::withMessages(['driver_id' => ['The selected driver is not available.']]);
        }

        if ($driver->status !== 'active') {
            throw ValidationException::withMessages([
                'driver_id' => [$driver->full_name.' is '.str_replace('_', ' ', $driver->status).' and cannot be allocated.'],
            ]);
        }

        if ($driver->license_expiry_date !== null && $driver->license_expiry_date->endOfDay()->lessThan($end)) {
            throw ValidationException::withMessages([
                'driver_id' => [$driver->full_name.'\'s licence expires on '.$driver->license_expiry_date->toDateString().', before this allocation ends.'],
            ]);
        }

        $this->assertFree('driver_id', $driver->full_name, (int) $driver->id, $start, $end, $ignoreId);
    }

    private function assertFree(string $column, string $label, int $id, Carbon $start, Carbon $end, ?int $ignoreId): void
    {
        $clash = VehicleAllocation::query()
            ->where($column, $id)
            ->blocking()
            ->overlapping($start, $end)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->orderBy('start_at')
            ->lockForUpdate()
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                $column => [sprintf(
                    '%s is already allocated from %s to %s (allocation #%d).',
                    $label,
                    $clash->start_at->format('Y-m-d H:i'),
                    $clash->end_at->format('Y-m-d H:i'),
                    $clash->id,
                )],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        $allowed = ['vehicle_id', 'driver_id', 'adm_request_id', 'start_at', 'end_at', 'status', 'purpose', 'destination', 'notes'];

        return array_intersect_key($data, array_flip($allowed));
    }
}
