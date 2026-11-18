<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public const STATUSES = ['available', 'maintenance', 'retired'];

    protected $attributes = ['status' => 'available'];

    protected $fillable = [
        'company_name',
        'registration_number',
        'make',
        'model',
        'year',
        'vehicle_type',
        'fuel_type',
        'chassis_number',
        'current_mileage',
        'driver_id',
        'insurance_policy_id',
        'revenue_license_expiry',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'current_mileage' => 'integer',
            'driver_id' => 'integer',
            'insurance_policy_id' => 'integer',
            'revenue_license_expiry' => 'date',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function insurancePolicy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class);
    }

    public function fuelCards(): HasMany
    {
        return $this->hasMany(FuelCard::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VehicleAllocation::class);
    }
}
