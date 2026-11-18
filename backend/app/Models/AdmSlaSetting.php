<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmSlaSetting extends Model
{
    protected $fillable = [
        'adm_category_id',
        'adm_request_type_id',
        'target_hours',
        'escalate_to_manager',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'adm_category_id' => 'integer',
            'adm_request_type_id' => 'integer',
            'target_hours' => 'integer',
            'escalate_to_manager' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdmCategory::class, 'adm_category_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(AdmRequestType::class, 'adm_request_type_id');
    }
}
