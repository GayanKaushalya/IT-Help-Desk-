<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves and lists HR requests.
 *
 * The employee list is always scoped to requester_id. The staff queue hides
 * confidential complaints unless the viewer is cleared for them or is the
 * assignee. Authorization for a single request is left to the controller and
 * HrRequestPolicy.
 */
class HrRequestQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, HrRequest>
     */
    public function paginateOwned(User $user, array $filters): LengthAwarePaginator
    {
        $query = HrRequest::query()
            ->forRequester((int) $user->id)
            ->with([
                'category',
                'requestType',
                'status',
                'assignee',
                'assignments' => fn ($assignments) => $assignments->current(),
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, HrRequest>
     */
    public function paginateForStaff(User $user, array $filters, bool $assignedToMeOnly = false): LengthAwarePaginator
    {
        $query = HrRequest::query()->with([
            'category',
            'requestType',
            'status',
            'assignee',
            'requester',
            'branch',
            'assignments' => fn ($assignments) => $assignments->current()->with('assignee.role'),
        ]);

        $this->applyStaffVisibility($query, $user);

        if ($assignedToMeOnly) {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->where('assigned_to', $user->id)
                    ->orWhereHas(
                        'assignments',
                        fn (Builder $assignments) => $assignments
                            ->whereNull('released_at')
                            ->where('assigned_to', $user->id),
                    );
            });
        }

        $this->applyFilters($query, $filters);
        $this->applyStaffFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters));
    }

    /**
     * Restricts a query to the requests this staff member may see.
     */
    public function applyStaffVisibility(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('hr.requests.view_any')) {
            $query->where('hr_requests.assigned_to', $user->id);
        }

        if (! $user->canViewConfidentialHr()) {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->where('hr_requests.is_confidential', false)
                    ->orWhere('hr_requests.assigned_to', $user->id);
            });
        }

        return $query;
    }

    /**
     * Looks a request up by numeric id or public reference. Visibility is
     * checked by the caller through the policy.
     */
    public function findVisible(int|string $identifier, bool $forStaff = false): HrRequest
    {
        $query = HrRequest::query();

        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('reference', $identifier);
        }

        $request = $query->first();

        if (! $request) {
            throw (new ModelNotFoundException)->setModel(HrRequest::class, [(string) $identifier]);
        }

        return $this->loadShowRelations($request, $forStaff);
    }

    /**
     * Tracking lookup by public reference such as HR-2026-000001.
     */
    public function findByReference(string $reference, bool $forStaff = false): HrRequest
    {
        $request = HrRequest::query()
            ->where('reference', strtoupper(trim($reference)))
            ->first();

        if (! $request) {
            throw (new ModelNotFoundException)->setModel(HrRequest::class, [$reference]);
        }

        return $this->loadShowRelations($request, $forStaff);
    }

    /**
     * Employee views never receive internal notes, referrals or status remarks.
     */
    public function loadShowRelations(HrRequest $request, bool $forStaff = false): HrRequest
    {
        $request->load([
            'category',
            'requestType',
            'status',
            'assignee.role',
            'resolver.role',
            'requester.role',
            'branch',
            'attachments',
            'assignments' => fn ($assignments) => $assignments
                ->current()
                ->with(['assignee.role', 'assigner.role'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id'),
            'statusHistories' => fn ($histories) => $histories
                ->with(['fromStatus', 'toStatus', 'changer.role'])
                ->orderBy('changed_at')
                ->orderBy('id'),
            'comments' => fn ($comments) => $forStaff
                ? $comments->with(['author.role', 'attachments'])->orderBy('created_at')->orderBy('id')
                : $comments->visibleToRequester()->where('is_system', false)
                    ->with(['author.role', 'attachments'])->orderBy('created_at')->orderBy('id'),
        ]);

        if ($forStaff) {
            $request->load([
                'escalatedTo.role',
                'assignments' => fn ($assignments) => $assignments
                    ->with(['assignee.role', 'assigner.role'])
                    ->orderByDesc('assigned_at')
                    ->orderByDesc('id'),
                'referrals' => fn ($referrals) => $referrals
                    ->with(['creator.role', 'assignee.role', 'responder.role'])
                    ->orderByDesc('created_at'),
            ]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? config('hrhelpdesk.pagination.default_per_page'));

        return max(1, min($perPage, (int) config('hrhelpdesk.pagination.max_per_page')));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $term = str_replace(['%', '_'], '', (string) $filters['search']);

            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('employee_name', 'like', $like)
                        ->orWhere('employee_id', 'like', $like);
                });
            }
        }

        $columns = [
            'status_id' => 'status_id',
            'category_id' => 'hr_category_id',
            'request_type_id' => 'hr_request_type_id',
        ];

        foreach ($columns as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyStaffFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (! empty($filters['unassigned'])) {
            $query->whereNull('assigned_to');
        }

        if (! empty($filters['overdue'])) {
            $query->overdue();
        }

        if (array_key_exists('confidential', $filters) && $filters['confidential'] !== null) {
            $query->where('is_confidential', (bool) $filters['confidential']);
        }
    }
}
