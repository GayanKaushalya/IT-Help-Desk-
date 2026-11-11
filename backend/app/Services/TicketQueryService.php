<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves and lists tickets the signed-in employee is allowed to see.
 *
 * The employee list is always scoped to requester_id. Authorization for a
 * single ticket is left to the controller/policy (404 vs 403).
 */
class TicketQueryService
{
    /**
     * @param  array{
     *     search: string|null,
     *     status_id: int|null,
     *     priority_id: int|null,
     *     category_id: int|null,
     *     date_from: string|null,
     *     date_to: string|null,
     *     per_page: int|null
     * }  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateOwned(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page']
            ?? (int) config('helpdesk.pagination.default_per_page');
        $perPage = max(1, min($perPage, (int) config('helpdesk.pagination.max_per_page')));

        $query = Ticket::query()
            ->forRequester((int) $user->id)
            ->with(['category', 'priority', 'status', 'assignee', 'assignments' => fn ($assignments) => $assignments->current()]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findVisible(int|string $identifier): Ticket
    {
        $query = Ticket::query();

        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('reference', $identifier);
        }

        $ticket = $query->first();

        if (! $ticket) {
            throw (new ModelNotFoundException)->setModel(Ticket::class, [(string) $identifier]);
        }

        $this->loadShowRelations($ticket);

        return $ticket;
    }

    public function loadShowRelations(Ticket $ticket): Ticket
    {
        $ticket->load([
            'category',
            'priority',
            'status',
            'assignee',
            'requester.role',
            'resolver.role',
            'verifications',
            'branch',
            'attachments',
            'assignments.assignee',
            'comments' => fn ($comments) => $comments
                ->visibleToRequester()
                ->where('is_system', false)
                ->with(['author.role', 'attachments'])
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return $ticket;
    }

    /**
     * @param  array{
     *     search: string|null,
     *     status_id: int|null,
     *     priority_id: int|null,
     *     category_id: int|null,
     *     date_from: string|null,
     *     date_to: string|null,
     *     per_page: int|null
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'])) {
            $term = str_replace(['%', '_'], '', (string) $filters['search']);

            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('subject', 'like', $like);
                });
            }
        }

        if ($filters['status_id']) {
            $query->where('status_id', $filters['status_id']);
        }

        if ($filters['priority_id']) {
            $query->where('priority_id', $filters['priority_id']);
        }

        if ($filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        if (filled($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }
    }
}
