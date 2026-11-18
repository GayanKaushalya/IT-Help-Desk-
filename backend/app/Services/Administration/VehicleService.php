<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Vehicle::query()->with(['driver', 'insurancePolicy']);

        if (filled($filters['search'] ?? null)) {
            $like = RegisterPagination::like((string) $filters['search']);
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('registration_number', 'like', $like)
                    ->orWhere('make', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('company_name', 'like', $like);
            });
        }

        foreach (['status', 'company_name', 'vehicle_type'] as $column) {
            if (filled($filters[$column] ?? null)) {
                $query->where($column, (string) $filters[$column]);
            }
        }

        return $query->orderBy('registration_number')->paginate(RegisterPagination::perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Vehicle
    {
        $vehicle = Vehicle::query()->create($data);

        $this->audit->log(
            event: 'vehicle.created',
            subject: $vehicle,
            newValues: ['registration_number' => $vehicle->registration_number],
            description: $actor->username.' added vehicle '.$vehicle->registration_number,
        );

        return $vehicle->load(['driver', 'insurancePolicy']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle $vehicle, User $actor, array $data): Vehicle
    {
        $vehicle->fill($data)->save();

        $this->audit->log(
            event: 'vehicle.updated',
            subject: $vehicle,
            newValues: ['registration_number' => $vehicle->registration_number, 'status' => $vehicle->status],
            description: $actor->username.' updated vehicle '.$vehicle->registration_number,
        );

        return $vehicle->load(['driver', 'insurancePolicy']);
    }

    public function delete(Vehicle $vehicle, User $actor): void
    {
        $hasUpcoming = VehicleAllocation::query()
            ->where('vehicle_id', $vehicle->id)
            ->blocking()
            ->where('end_at', '>', now())
            ->exists();

        if ($hasUpcoming) {
            throw ValidationException::withMessages([
                'vehicle' => ['This vehicle has current or upcoming allocations. Cancel them before removing the vehicle.'],
            ]);
        }

        $vehicle->delete();

        $this->audit->log(
            event: 'vehicle.deleted',
            subject: $vehicle,
            description: $actor->username.' removed vehicle '.$vehicle->registration_number,
        );
    }
}
