<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmRequestType extends Model
{
    protected $fillable = [
        'adm_category_id',
        'slug',
        'name',
        'description',
        'is_active',
        'sort_order',
        'sla_hours',
    ];

    protected function casts(): array
    {
        return [
            'adm_category_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'sla_hours' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdmCategory::class, 'adm_category_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AdmRequest::class, 'adm_request_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
