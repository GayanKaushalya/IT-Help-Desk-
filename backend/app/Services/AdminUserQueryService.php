<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Directory listing and user-detail aggregates for the admin platform.
 */
class AdminUserQueryService
{
    public const RECENT_TICKET_LIMIT = 8;

    public const AUDIT_LIMIT = 25;

    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * @param  array{
     *     search: string|null,
     *     branch_id: int|null,
     *     role_id: int|null,
     *     account_status: string|null,
     *     per_page: int|null
     * }  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page']
            ?? (int) config('helpdesk.pagination.default_per_page');
        $perPage = max(1, min($perPage, (int) config('helpdesk.pagination.max_per_page')));

        $query = User::query()->with(['role', 'branch', 'department']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findForAdmin(int|string $identifier): User
    {
        $query = User::query()->with(['role', 'branch', 'department']);

        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('username', $identifier);
        }

        return $query->firstOrFail();
    }

    /**
     * @return array<string, int>
     */
    public function ticketStatistics(User $user): array
    {
        return $this->dashboard->statistics($user);
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function recentTickets(User $user): Collection
    {
        return $this->dashboard->recentTickets($user);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function auditHistory(User $user): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->where('auditable_type', $user->getMorphClass())
                        ->where('auditable_id', $user->id);
                })->orWhere(function (Builder $inner) use ($user): void {
                    $inner->where('user_id', $user->id)
                        ->where('event', 'like', 'user.%');
                });
            })
            ->orderByDesc('id')
            ->limit(self::AUDIT_LIMIT)
            ->get();
    }

    /**
     * @param  array{
     *     search: string|null,
     *     branch_id: int|null,
     *     role_id: int|null,
     *     account_status: string|null,
     *     per_page: int|null
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $term = str_replace(['%', '_'], '', (string) $filters['search']);

            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('full_name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('employee_id', 'like', $like)
                        ->orWhere('epf_number', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            }
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['role_id'])) {
            $query->where('role_id', (int) $filters['role_id']);
        }

        $status = $filters['account_status'] ?? null;

        if ($status === 'active') {
            $query->where('is_active', true)->where('is_approved', true);
        } elseif ($status === 'inactive') {
            $query->where('is_approved', true)->where('is_active', false);
        } elseif ($status === 'pending') {
            $query->where('is_approved', false);
        }
    }
}
