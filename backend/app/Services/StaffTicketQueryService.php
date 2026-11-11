<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Organisation-wide ticket queries for the IT service desk.
 *
 * Employee list/show loaders stay in TicketQueryService so internal notes
 * cannot leak through requester-facing endpoints.
 */
class StaffTicketQueryService
{
    public function __construct(private readonly TicketStatusTransition $transitions) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateQueue(array $filters, ?int $assignedTo = null): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? config('helpdesk.pagination.default_per_page'));
        $perPage = max(1, min($perPage, (int) config('helpdesk.pagination.max_per_page')));

        $query = Ticket::query()->with([
            'category',
            'priority',
            'status',
            'assignee',
            'requester.role',
            'branch',
            'assignments' => fn ($assignments) => $assignments->current()->with('assignee.role'),
        ]);

        if ($assignedTo !== null) {
            $query->assignedTo($assignedTo);
        }

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForStaff(int|string $identifier): Ticket
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

        return $this->loadStaffRelations($ticket);
    }

    public function loadStaffRelations(Ticket $ticket): Ticket
    {
        $ticket->load([
            'category',
            'priority',
            'status',
            'assignee.role',
            'requester.role',
            'resolver.role',
            'branch',
            'attachments',
            'assignments' => fn ($assignments) => $assignments->current()->with('assignee.role'),
            'verifications',
            'comments' => fn ($comments) => $comments
                ->where('is_system', false)
                ->with(['author.role', 'attachments'])
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return $ticket;
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function allowedTransitions(Ticket $ticket, ?User $actor = null): array
    {
        $ticket->loadMissing('status');
        $slugs = $this->transitions->targetsFor($ticket->status?->slug ?? '');
        $slugs = array_values(array_filter($slugs, fn (string $slug): bool => $slug !== 'closed'));

        if ($slugs === []) {
            return [];
        }

        return TicketStatus::query()
            ->whereIn('slug', $slugs)
            ->ordered()
            ->get(['id', 'slug', 'name'])
            ->map(fn (TicketStatus $status) => [
                'id' => (int) $status->id,
                'slug' => $status->slug,
                'name' => $status->name,
            ])
            ->all();
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
                        ->orWhere('subject', 'like', $like);
                });
            }
        }

        foreach (['status_id', 'priority_id', 'category_id', 'branch_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, (int) $filters[$column]);
            }
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (! empty($filters['unassigned'])) {
            $query->whereNull('assigned_to');
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }
    }
}
