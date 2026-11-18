<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CctvAccessLog extends Model
{
    protected $fillable = [
        'adm_request_id',
        'cctv_approval_id',
        'user_id',
        'action',
        'notes',
        'ip_address',
    ];

    public function admRequest(): BelongsTo
    {
        return $this->belongsTo(AdmRequest::class, 'adm_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
