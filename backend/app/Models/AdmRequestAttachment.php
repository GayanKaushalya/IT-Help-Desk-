<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmRequestAttachment extends Model
{
    protected $fillable = [
        'adm_request_id',
        'adm_request_comment_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'adm_request_id' => 'integer',
            'adm_request_comment_id' => 'integer',
            'uploaded_by' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AdmRequest::class, 'adm_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
