<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAllocation extends Model
{
    public const STATUSES = ['scheduled', 'active', 'completed', 'cancelled'];

    protected $attributes = ['status' => 'scheduled'];

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'adm_request_id',
        'start_at',
        'end_at',
        'status',
        'purpose',
        'destination',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'driver_id' => 'integer',
            'adm_request_id' => 'integer',
            'created_by' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function admRequest(): BelongsTo
    {
        return $this->belongsTo(AdmRequest::class, 'adm_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Allocations that still hold the vehicle/driver (everything but cancelled). */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Allocations whose window intersects [start, end) — back-to-back bookings do not overlap. */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
