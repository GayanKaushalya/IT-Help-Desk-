<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsurancePolicy extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'expired', 'cancelled'];

    public const RENEWAL_STATUSES = ['not_due', 'pending', 'in_progress', 'renewed'];

    protected $attributes = ['status' => 'active', 'renewal_status' => 'not_due'];

    protected $fillable = [
        'company_name',
        'insurance_type',
        'provider',
        'policy_number',
        'insured_asset',
        'vehicle_registration',
        'start_date',
        'expiry_date',
        'premium_amount',
        'sum_insured',
        'document_disk',
        'document_path',
        'document_name',
        'responsible_officer_id',
        'renewal_status',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'premium_amount' => 'decimal:2',
            'sum_insured' => 'decimal:2',
            'start_date' => 'date',
            'expiry_date' => 'date',
            'responsible_officer_id' => 'integer',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function responsibleOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_officer_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(InsurancePolicyHistory::class, 'policy_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(InsuranceReminder::class, 'policy_id');
    }

    /** Policies that still need renewal attention (not cancelled, not already renewed). */
    public function scopeNeedingRenewal(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expiry_date')
            ->where('status', '!=', 'cancelled')
            ->where('renewal_status', '!=', 'renewed');
    }

    public function hasDocument(): bool
    {
        return $this->document_path !== null && $this->document_path !== '';
    }
}
