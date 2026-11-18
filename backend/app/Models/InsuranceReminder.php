<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceReminder extends Model
{
    protected $fillable = ['policy_id', 'remind_days', 'reminded_on'];

    protected function casts(): array
    {
        return [
            'policy_id' => 'integer',
            'remind_days' => 'integer',
            'reminded_on' => 'date',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class, 'policy_id');
    }
}
