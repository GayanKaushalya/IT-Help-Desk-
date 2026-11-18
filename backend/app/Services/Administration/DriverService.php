<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\Driver;
use App\Models\User;
use App\Models\VehicleAllocation;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DriverService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Driver>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Driver::query();

        if (filled($filters['search'] ?? null)) {
            $like = RegisterPagination::like((string) $filters['search']);
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('full_name', 'like', $like)
                    ->orWhere('license_number', 'like', $like)
                    ->orWhere('employee_id', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        foreach (['status', 'company_name'] as $column) {
            if (filled($filters[$column] ?? null)) {
                $query->where($column, (string) $filters[$column]);
            }
        }

        return $query->orderBy('full_name')->paginate(RegisterPagination::perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Driver
    {
        $driver = Driver::query()->create($data);

        $this->audit->log(
            event: 'driver.created',
            subject: $driver,
            newValues: ['full_name' => $driver->full_name],
            description: $actor->username.' added driver '.$driver->full_name,
        );

        return $driver;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Driver $driver, User $actor, array $data): Driver
    {
        $driver->fill($data)->save();

        $this->audit->log(
            event: 'driver.updated',
            subject: $driver,
            newValues: ['full_name' => $driver->full_name, 'status' => $driver->status],
            description: $actor->username.' updated driver '.$driver->full_name,
        );

        return $driver;
    }

    public function delete(Driver $driver, User $actor): void
    {
        $hasUpcoming = VehicleAllocation::query()
            ->where('driver_id', $driver->id)
            ->blocking()
            ->where('end_at', '>', now())
            ->exists();

        if ($hasUpcoming) {
            throw ValidationException::withMessages([
                'driver' => ['This driver has current or upcoming allocations. Cancel them before removing the driver.'],
            ]);
        }

        $driver->delete();

        $this->audit->log(
            event: 'driver.deleted',
            subject: $driver,
            description: $actor->username.' removed driver '.$driver->full_name,
        );
    }
}
