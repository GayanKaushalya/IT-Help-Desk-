<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class AdmRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'requester_id',
        'employee_id',
        'employee_name',
        'department_name',
        'designation',
        'branch_id',
        'department_id',
        'adm_category_id',
        'adm_request_type_id',
        'status_id',
        'subject',
        'description',
        'required_date',
        'company_name',
        'assigned_to',
        'assigned_at',
        'due_at',
        'resolution_summary',
        'resolved_by',
        'resolved_at',
        'closed_by',
        'closed_at',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'adm_category_id' => 'integer',
            'adm_request_type_id' => 'integer',
            'status_id' => 'integer',
            'required_date' => 'date',
            'assigned_to' => 'integer',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'resolved_by' => 'integer',
            'resolved_at' => 'datetime',
            'closed_by' => 'integer',
            'closed_at' => 'datetime',
            'details' => 'array',
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
        return $this->belongsTo(AdmCategory::class, 'adm_category_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(AdmRequestType::class, 'adm_request_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AdmRequestStatus::class, 'status_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AdmRequestComment::class, 'adm_request_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AdmRequestAttachment::class, 'adm_request_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AdmRequestAssignment::class, 'adm_request_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AdmRequestStatusHistory::class, 'adm_request_id');
    }

    public function cctvApproval(): HasOne
    {
        return $this->hasOne(CctvApproval::class, 'adm_request_id');
    }

    public function isCctv(): bool
    {
        $this->loadMissing('category');

        return $this->category?->slug === 'cctv-surveillance';
    }

    public function scopeForRequester(Builder $query, int $userId): Builder
    {
        return $query->where('requester_id', $userId);
    }

    /** Open = not closed/rejected/cancelled (includes resolved awaiting employee confirmation). */
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
            'awaiting-external-service-provider',
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
            'assigned_to' => ['Assign this request to a specific Administration staff member before closing.'],
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
