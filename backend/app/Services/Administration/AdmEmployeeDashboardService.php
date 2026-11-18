<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\AdmRequest;
use App\Models\AdmRequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Employee-facing Administration home metrics. Always scoped to the signed-in requester.
 */
class AdmEmployeeDashboardService
{
    public const RECENT_LIMIT = 8;

    /**
     * @return array{
     *     statistics: array{total: int, open: int, in_progress: int, awaiting_verification: int, closed: int},
     *     recent_requests: Collection<int, AdmRequest>
     * }
     */
    public function overview(User $user): array
    {
        return [
            'statistics' => $this->statistics($user),
            'recent_requests' => $this->recentRequests($user),
        ];
    }

    /**
     * @return array{total: int, open: int, in_progress: int, awaiting_verification: int, closed: int}
     */
    public function statistics(User $user): array
    {
        $countsByStatus = AdmRequest::query()
            ->forRequester((int) $user->id)
            ->selectRaw('status_id, COUNT(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        $statuses = AdmRequestStatus::query()
            ->get(['id', 'slug', 'is_open', 'is_terminal'])
            ->keyBy('id');

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

            if ($status->slug === 'resolved') {
                $stats['awaiting_verification'] += $count;
            }

            if ($status->is_terminal || $status->slug === 'closed') {
                $stats['closed'] += $count;
            }
        }

        return $stats;
    }

    /**
     * @return Collection<int, AdmRequest>
     */
    public function recentRequests(User $user): Collection
    {
        return AdmRequest::query()
            ->forRequester((int) $user->id)
            ->with([
                'category',
                'requestType',
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
