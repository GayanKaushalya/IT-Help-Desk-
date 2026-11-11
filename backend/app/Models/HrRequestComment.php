<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrRequestComment extends Model
{
    protected $fillable = [
        'hr_request_id',
        'author_id',
        'body',
        'is_internal',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'hr_request_id' => 'integer',
            'author_id' => 'integer',
            'is_internal' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class, 'hr_request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HrRequestAttachment::class, 'hr_request_comment_id');
    }

    public function scopeVisibleToRequester(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }
}
