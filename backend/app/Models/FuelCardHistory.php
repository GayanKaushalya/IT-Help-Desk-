<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelCardHistory extends Model
{
    protected $table = 'fuel_card_history';

    protected $fillable = ['fuel_card_id', 'changed_by', 'event', 'notes', 'changed_at'];

    protected function casts(): array
    {
        return [
            'fuel_card_id' => 'integer',
            'changed_by' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(FuelCard::class, 'fuel_card_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
