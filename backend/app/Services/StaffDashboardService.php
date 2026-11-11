<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Organisation-wide service-desk metrics for the IT administration home.
 */
class StaffDashboardService
{
    public const RECENT_LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $byStatus = $this->countByLookup(TicketStatus::class, 'status_id');
        $byPriority = $this->countByLookup(TicketPriority::class, 'priority_id');
        $statusBySlug = collect($byStatus)->keyBy('slug');
        $priorityBySlug = collect($byPriority)->keyBy('slug');
        $closedStatusIds = TicketStatus::query()->where('is_closed', true)->pluck('id')->all();
        // "Resolved" rows are awaiting requester verification (requires_verification).
        $awaitingVerification = (int) ($statusBySlug->get('resolved')['count'] ?? 0);

        return [
            'statistics' => [
                'total' => (int) collect($byStatus)->sum('count'),
                'open' => (int) ($statusBySlug->get('new')['count'] ?? 0),
                'assigned' => (int) ($statusBySlug->get('assigned')['count'] ?? 0),
                'in_progress' => (int) ($statusBySlug->get('in-progress')['count'] ?? 0),
                'awaiting_user' => (int) ($statusBySlug->get('awaiting-user')['count'] ?? 0),
                'resolved' => $awaitingVerification,
                'awaiting_verification' => $awaitingVerification,
                'closed' => (int) collect($byStatus)
                    ->whereIn('id', $closedStatusIds)
                    ->sum('count'),
                'critical' => (int) ($priorityBySlug->get('critical')['count'] ?? 0),
            ],
            'charts' => [
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
                'by_category' => $this->countByLookup(TicketCategory::class, 'category_id'),
                'by_branch' => $this->countByLookup(Branch::class, 'branch_id'),
                'over_time' => $this->ticketsOverTime(),
                'workload' => $this->workload(),
            ],
            'recent_tickets' => Ticket::query()
                ->with([
                    'category',
                    'priority',
                    'status',
                    'assignee',
                    'requester.role',
                    'branch',
                    'assignments' => fn ($assignments) => $assignments->current()->with('assignee'),
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::RECENT_LIMIT)
                ->get(),
        ];
    }

    /**
     * @param  class-string  $model
     * @return list<array{id: int, name: string, slug: string|null, count: int, color_token: string|null}>
     */
    private function countByLookup(string $model, string $foreignKey): array
    {
        $counts = Ticket::query()
            ->selectRaw($foreignKey.' as lookup_id, COUNT(*) as aggregate')
            ->groupBy($foreignKey)
            ->pluck('aggregate', 'lookup_id');

        $query = $model::query();

        if (method_exists($model, 'scopeOrdered')) {
            $query->ordered();
        }

        return $query->get()->map(function ($row) use ($counts) {
            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => $row->slug ?? null,
                'count' => (int) ($counts[$row->id] ?? $counts[(string) $row->id] ?? 0),
                'color_token' => $row->color_token ?? null,
            ];
        })->all();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function ticketsOverTime(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $driver = Ticket::query()->getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            $grouped = Ticket::query()
                ->where('created_at', '>=', $start)
                ->selectRaw('CONVERT(date, created_at) as day, COUNT(*) as aggregate')
                ->groupBy(DB::raw('CONVERT(date, created_at)'))
                ->pluck('aggregate', 'day');
        } else {
            $grouped = Ticket::query()
                ->where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->pluck('aggregate', 'day');
        }

        $series = [];

        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $count = $grouped[$key]
                ?? $grouped[$day->format('Y-m-d H:i:s')]
                ?? null;

            if ($count === null) {
                foreach ($grouped as $rawDay => $aggregate) {
                    if (Carbon::parse((string) $rawDay)->toDateString() === $key) {
                        $count = $aggregate;
                        break;
                    }
                }
            }

            $series[] = [
                'date' => $key,
                'count' => (int) ($count ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{id: int, full_name: string, open_tickets: int, total_assigned: int}>
     */
    private function workload(): array
    {
        return User::query()
            ->staff()
            ->withCount([
                'assignedTickets as total_assigned',
                'assignedTickets as open_tickets' => fn ($query) => $query->open(),
            ])
            ->orderByDesc('open_tickets')
            ->orderBy('full_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'full_name' => $user->full_name,
                'open_tickets' => (int) $user->open_tickets,
                'total_assigned' => (int) $user->total_assigned,
            ])
            ->all();
    }
}
