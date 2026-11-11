<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'subject',
        'description',
        'requester_id',
        'branch_id',
        'department_id',
        'category_id',
        'priority_id',
        'status_id',
        'assigned_to',
        'assigned_at',
        'source',
        'contact_location',
        'preferred_contact_method',
        'contact_phone',
        'additional_details',
        'issue_type',
        'item_type',
        'item_other',
        'asset_tag',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'category_id' => 'integer',
            'priority_id' => 'integer',
            'status_id' => 'integer',
            'assigned_to' => 'integer',
            'resolved_by' => 'integer',
            'closed_by' => 'integer',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_reopened' => 'boolean',
            'reopen_count' => 'integer',
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function isAssignedToTeam(): bool
    {
        // Never query per row on list/dashboard payloads — callers that need
        // accuracy must eager-load current assignments.
        if (! $this->relationLoaded('assignments')) {
            return false;
        }

        return $this->assignments->whereNull('released_at')->count() > 1;
    }

    /**
     * A ticket is closable only when one IT member owns it — not the whole desk pool.
     */
    public function hasIndividualAssignee(): bool
    {
        if ($this->assigned_to === null) {
            return false;
        }

        if ($this->relationLoaded('assignments')) {
            return $this->assignments->whereNull('released_at')->count() <= 1;
        }

        return $this->assignments()->whereNull('released_at')->count() <= 1;
    }

    public function assertIndividualAssigneeForClose(): void
    {
        if ($this->hasIndividualAssignee()) {
            return;
        }

        throw ValidationException::withMessages([
            'assigned_to' => ['Assign this ticket to a specific IT staff member before closing.'],
        ]);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(TicketVerification::class);
    }

    /** The verification code currently awaiting the requester. */
    public function activeVerification(): HasOne
    {
        return $this->hasOne(TicketVerification::class)
            ->where('status', 'pending')
            ->latestOfMany();
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeForRequester(Builder $query, int $userId): Builder
    {
        return $query->where('requester_id', $userId);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('assigned_to', $userId)
                ->orWhereHas(
                    'assignments',
                    fn (Builder $assignments) => $assignments->current()->where('assigned_to', $userId),
                );
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status_id', static::statusIdsWhere('is_open', true));
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status_id', static::statusIdsWhere('slug', 'in-progress'));
    }

    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query->whereIn(
            'status_id',
            TicketStatus::query()
                ->where('is_resolved', true)
                ->where('requires_verification', true)
                ->where('is_closed', false)
                ->select('id'),
        );
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status_id', static::statusIdsWhere('is_closed', true));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\TicketStatus>
     */
    private static function statusIdsWhere(string $column, mixed $value): Builder
    {
        return TicketStatus::query()->where($column, $value)->select('id');
    }
}
