<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /** Memoised per instance; strict mode forbids undeclared dynamic reads. */
    private ?bool $hrComplaintAccess = null;

    protected $fillable = [
        'full_name',
        'username',
        'employee_id',
        'epf_number',
        'email',
        'phone',
        'password',
        'role_id',
        'branch_id',
        'department_id',
        'job_title',
        'avatar_path',
        'is_active',
        'is_approved',
    ];

    /**
     * Never serialised into an API response.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            // Hashes on assignment, so a plain password can never be persisted.
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Tickets this user raised. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /** Tickets currently owned by this user as an IT agent. */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'author_id');
    }

    /** HR requests and complaints this user raised. */
    public function hrRequests(): HasMany
    {
        return $this->hasMany(HrRequest::class, 'requester_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // -----------------------------------------------------------------
    // Authorization helpers
    // -----------------------------------------------------------------

    public function hasRole(RoleSlug|string ...$roles): bool
    {
        $slugs = array_map(
            fn (RoleSlug|string $role) => $role instanceof RoleSlug ? $role->value : $role,
            $roles
        );

        return in_array($this->resolveRole()?->slug, $slugs, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleSlug::SuperAdmin);
    }

    public function isStaff(): bool
    {
        return in_array($this->resolveRole()?->slug, RoleSlug::staffSlugs(), true);
    }

    public function isHrStaff(): bool
    {
        return in_array($this->resolveRole()?->slug, RoleSlug::hrStaffSlugs(), true);
    }

    /**
     * Confidential HR complaints are visible to managers, holders of the
     * view_confidential permission, and users granted explicit complaint access.
     */
    public function canViewConfidentialHr(): bool
    {
        if ($this->isSuperAdmin() || $this->hasRole(RoleSlug::HrManager, RoleSlug::Administrator)) {
            return true;
        }

        if ($this->hasPermission('hr.requests.view_confidential')) {
            return true;
        }

        return $this->hasHrComplaintAccess();
    }

    public function hasHrComplaintAccess(): bool
    {
        return $this->hrComplaintAccess ??= HrComplaintAccess::query()
            ->where('user_id', $this->getKey())
            ->exists();
    }

    /**
     * Super admins implicitly hold every permission, which keeps the seeded
     * permission matrix from being able to lock everyone out of the system.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $role = $this->resolveRole();

        if (! $role) {
            return false;
        }

        $role->loadMissing('permissions');

        return $role->permissions->contains('slug', $slug);
    }

    /**
     * Authorization runs on every request, including from middleware where the
     * relation may not have been eager loaded. loadMissing() fetches it without
     * tripping the strict-mode lazy loading guard.
     */
    private function resolveRole(): ?Role
    {
        $this->loadMissing('role');

        return $this->getRelation('role');
    }

    /**
     * All permission slugs granted to this user, for the session payload.
     *
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        $role = $this->resolveRole();

        if (! $role) {
            return [];
        }

        $role->loadMissing('permissions');

        return $role->permissions->pluck('slug')->all();
    }

    /**
     * Only approved and active accounts may authenticate.
     */
    public function canAccessSystem(): bool
    {
        return $this->is_active && $this->is_approved;
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_approved', true);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $role) => $role->whereIn('slug', RoleSlug::staffSlugs()));
    }

    public function scopeHrStaff(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $role) => $role->whereIn('slug', RoleSlug::hrStaffSlugs()));
    }
}
