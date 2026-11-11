<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDepartmentReferral extends Model
{
    protected $fillable = [
        'hr_request_id',
        'department_slug',
        'status',
        'created_by',
        'assigned_to',
        'brief',
        'response',
        'responded_at',
        'responded_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'hr_request_id' => 'integer',
            'created_by' => 'integer',
            'assigned_to' => 'integer',
            'responded_at' => 'datetime',
            'responded_by' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class, 'hr_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
