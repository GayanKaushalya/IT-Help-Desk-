<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmRequestStatusHistory extends Model
{
    protected $fillable = [
        'adm_request_id',
        'from_status_id',
        'to_status_id',
        'changed_by',
        'remarks',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'adm_request_id' => 'integer',
            'from_status_id' => 'integer',
            'to_status_id' => 'integer',
            'changed_by' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AdmRequest::class, 'adm_request_id');
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(AdmRequestStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(AdmRequestStatus::class, 'to_status_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
