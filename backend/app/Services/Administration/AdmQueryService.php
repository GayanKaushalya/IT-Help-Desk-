<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\AdmRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves and lists Administration requests.
 *
 * The employee list is always scoped to requester_id. The staff queue is limited
 * to staff holding adm.requests.view_any (or the assignee). Authorization for a single request is left to the controller and
 * AdmRequestPolicy.
 */
class AdmQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AdmRequest>
     */
    public function paginateOwned(User $user, array $filters): LengthAwarePaginator
    {
        $query = AdmRequest::query()
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
     * @return LengthAwarePaginator<int, AdmRequest>
     */
    public function paginateForStaff(User $user, array $filters, bool $assignedToMeOnly = false): LengthAwarePaginator
    {
        $query = AdmRequest::query()->with([
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
        if (! $user->hasPermission('adm.requests.view_any')) {
            $query->where('adm_requests.assigned_to', $user->id);
        }

        return $query;
    }

    /**
     * Looks a request up by numeric id or public reference. Visibility is
     * checked by the caller through the policy.
     */
    public function findVisible(int|string $identifier, bool $forStaff = false): AdmRequest
    {
        $query = AdmRequest::query();

        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('reference', $identifier);
        }

        $request = $query->first();

        if (! $request) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [(string) $identifier]);
        }

        return $this->loadShowRelations($request, $forStaff);
    }

    /**
     * Tracking lookup by public reference such as ADM-2026-000001.
     */
    public function findByReference(string $reference, bool $forStaff = false): AdmRequest
    {
        $request = AdmRequest::query()
            ->where('reference', strtoupper(trim($reference)))
            ->first();

        if (! $request) {
            throw (new ModelNotFoundException)->setModel(AdmRequest::class, [$reference]);
        }

        return $this->loadShowRelations($request, $forStaff);
    }

    /**
     * Employee views never receive internal notes or status remarks.
     */
    public function loadShowRelations(AdmRequest $request, bool $forStaff = false): AdmRequest
    {
        $request->load([
            'category',
            'requestType',
            'status',
            'assignee.role',
            'resolver.role',
            'closer.role',
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
            $request->load(['cctvApproval.decider']);
            $request->load([
                'assignments' => fn ($assignments) => $assignments
                    ->with(['assignee.role', 'assigner.role'])
                    ->orderByDesc('assigned_at')
                    ->orderByDesc('id'),
            ]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? config('administrationhelpdesk.pagination.default_per_page'));

        return max(1, min($perPage, (int) config('administrationhelpdesk.pagination.max_per_page')));
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
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('employee_name', 'like', $like)
                        ->orWhere('employee_id', 'like', $like);
                });
            }
        }

        $columns = [
            'status_id' => 'status_id',
            'category_id' => 'adm_category_id',
            'request_type_id' => 'adm_request_type_id',
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
    }
}
