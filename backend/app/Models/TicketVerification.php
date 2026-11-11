<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketVerification extends Model
{
    protected $fillable = [
        'ticket_id',
        'code_hash',
        'code_last_four',
        'code_ciphertext',
        'issued_by',
        'issued_to',
        'verified_by',
        'status',
        'method',
        'issued_at',
        'expires_at',
        'verified_at',
        'attempts',
        'last_attempt_at',
        'blocked_until',
    ];

    /**
     * The hash and any remnant of the plaintext must never leave the server.
     *
     * @var list<string>
     */
    protected $hidden = [
        'code_hash',
        'code_last_four',
        'code_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'blocked_until' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === VerificationStatus::Pending && ! $this->isExpired();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VerificationStatus::Pending->value);
    }
}
