<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrRequestAssignment extends Model
{
    protected $fillable = [
        'hr_request_id',
        'assigned_to',
        'assigned_by',
        'note',
        'assigned_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'hr_request_id' => 'integer',
            'assigned_to' => 'integer',
            'assigned_by' => 'integer',
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class, 'hr_request_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }
}
