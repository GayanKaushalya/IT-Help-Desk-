<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'on_leave', 'inactive'];

    protected $attributes = ['status' => 'active'];

    protected $fillable = [
        'company_name',
        'full_name',
        'employee_id',
        'license_number',
        'license_class',
        'license_expiry_date',
        'phone',
        'email',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry_date' => 'date',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
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
