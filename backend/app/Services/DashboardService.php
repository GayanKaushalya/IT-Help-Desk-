<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Employee-facing dashboard data.
 *
 * Every query is locked to the signed-in requester. Staff who visit this
 * endpoint still only see tickets they raised, never the organisation queue.
 */
class DashboardService
{
    public const RECENT_LIMIT = 8;

    /**
     * @return array{statistics: array<string, int>, recent_tickets: Collection<int, Ticket>}
     */
    public function overview(User $user): array
    {
        return [
            'statistics' => $this->statistics($user),
            'recent_tickets' => $this->recentTickets($user),
        ];
    }

    /**
     * One grouped count instead of five round-trips to SQL Server.
     *
     * @return array{total: int, open: int, in_progress: int, awaiting_verification: int, closed: int}
     */
    public function statistics(User $user): array
    {
        $countsByStatus = Ticket::query()
            ->forRequester((int) $user->id)
            ->selectRaw('status_id, COUNT(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        $statuses = TicketStatus::query()->get(['id', 'slug', 'is_open', 'is_resolved', 'is_closed', 'requires_verification'])->keyBy('id');

        $stats = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'awaiting_verification' => 0,
            'closed' => 0,
        ];

        foreach ($countsByStatus as $statusId => $aggregate) {
            $count = (int) $aggregate;
            $stats['total'] += $count;

            $status = $statuses->get((int) $statusId) ?? $statuses->get($statusId);

            if (! $status) {
                continue;
            }

            if ($status->is_open) {
                $stats['open'] += $count;
            }

            if ($status->slug === 'in-progress') {
                $stats['in_progress'] += $count;
            }

            if ($status->is_resolved && $status->requires_verification && ! $status->is_closed) {
                $stats['awaiting_verification'] += $count;
            }

            if ($status->is_closed) {
                $stats['closed'] += $count;
            }
        }

        return $stats;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function recentTickets(User $user): Collection
    {
        return Ticket::query()
            ->forRequester((int) $user->id)
            ->with([
                'category',
                'priority',
                'status',
                'assignee',
                'assignments' => fn ($assignments) => $assignments->current(),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }
}
