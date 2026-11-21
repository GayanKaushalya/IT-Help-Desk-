<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class HrRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'requester_id',
        'employee_id',
        'employee_name',
        'department_name',
        'designation',
        'contact_phone',
        'branch_id',
        'department_id',
        'hr_category_id',
        'hr_request_type_id',
        'status_id',
        'description',
        'is_confidential',
        'assigned_to',
        'assigned_at',
        'due_at',
        'escalated_at',
        'escalated_to',
        'resolution_summary',
        'resolved_by',
        'resolved_at',
        'closed_by',
        'closed_at',
        'verification_due_at',
    ];

    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'hr_category_id' => 'integer',
            'hr_request_type_id' => 'integer',
            'status_id' => 'integer',
            'is_confidential' => 'boolean',
            'assigned_to' => 'integer',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'escalated_to' => 'integer',
            'resolved_by' => 'integer',
            'resolved_at' => 'datetime',
            'closed_by' => 'integer',
            'closed_at' => 'datetime',
            'verification_due_at' => 'datetime',
        ];
    }

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

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
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
        return $this->belongsTo(HrCategory::class, 'hr_category_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(HrRequestType::class, 'hr_request_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(HrRequestStatus::class, 'status_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(HrRequestComment::class, 'hr_request_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HrRequestAttachment::class, 'hr_request_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(HrRequestAssignment::class, 'hr_request_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(HrRequestStatusHistory::class, 'hr_request_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(HrDepartmentReferral::class, 'hr_request_id');
    }

    public function scopeForRequester(Builder $query, int $userId): Builder
    {
        return $query->where('requester_id', $userId);
    }

    /** Open = not closed/rejected (includes resolved awaiting employee confirmation). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas(
            'status',
            fn (Builder $status) => $status->where('is_terminal', false),
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $status) => $status->whereIn('slug', [
            'submitted',
            'under-review',
        ]));
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $status) => $status->whereIn('slug', [
            'assigned',
            'in-progress',
            'awaiting-employee-information',
            'awaiting-other-department',
        ]));
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $status) => $status->whereIn('slug', [
            'resolved',
            'closed',
        ]));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false));
    }

    public function scopeComplaints(Builder $query): Builder
    {
        return $query->whereHas('category', fn (Builder $category) => $category->where('slug', 'employee-complaints'));
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! ($this->status?->is_terminal ?? false);
    }

    public function isAssignedToTeam(): bool
    {
        if (! $this->relationLoaded('assignments')) {
            return false;
        }

        return $this->assignments->whereNull('released_at')->count() > 1;
    }

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
            'assigned_to' => ['Assign this request to a specific HR staff member before closing.'],
        ]);
    }

    public function isAwaitingVerification(): bool
    {
        $this->loadMissing('status');

        return $this->status?->slug === 'resolved'
            && ! ($this->status?->is_terminal ?? false)
            && $this->closed_at === null;
    }
}
