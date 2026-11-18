<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'sort_order',
        'default_sla_hours',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'default_sla_hours' => 'integer',
        ];
    }

    public function requestTypes(): HasMany
    {
        return $this->hasMany(AdmRequestType::class, 'adm_category_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AdmRequest::class, 'adm_category_id');
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
