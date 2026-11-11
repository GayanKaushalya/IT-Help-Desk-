<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSlaSetting extends Model
{
    protected $fillable = [
        'hr_category_id',
        'hr_request_type_id',
        'target_hours',
        'escalate_to_manager',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hr_category_id' => 'integer',
            'hr_request_type_id' => 'integer',
            'target_hours' => 'integer',
            'escalate_to_manager' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HrCategory::class, 'hr_category_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(HrRequestType::class, 'hr_request_type_id');
    }
}
