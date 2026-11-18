<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A company fuel card. Only an internal card id and a masked reference are
 * kept; the full card number and PIN are never stored.
 */
class FuelCard extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'suspended', 'lost', 'cancelled'];

    protected $attributes = ['status' => 'active'];

    protected $fillable = [
        'company_name',
        'card_number',
        'masked_card_ref',
        'provider',
        'vehicle_id',
        'driver_id',
        'assigned_user_id',
        'monthly_limit',
        'issued_at',
        'expiry_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'driver_id' => 'integer',
            'assigned_user_id' => 'integer',
            'monthly_limit' => 'decimal:2',
            'issued_at' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(FuelCardHistory::class);
    }
}
