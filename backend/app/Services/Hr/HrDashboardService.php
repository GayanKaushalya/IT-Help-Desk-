<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrCategory;
use App\Models\HrRequest;
use App\Models\HrRequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * HR service-desk metrics. Every figure respects the viewer's confidential
 * complaint visibility so counts never reveal hidden cases.
 */
class HrDashboardService
{
    public const RECENT_LIMIT = 10;

    public function __construct(private readonly HrRequestQueryService $queries) {}

    /**
     * @return array{
     *     statistics: array<string, int>,
     *     recent_requests: \Illuminate\Database\Eloquent\Collection<int, HrRequest>,
     *     by_category: list<array<string, mixed>>,
     *     by_status: list<array<string, mixed>>
     * }
     */
    public function overview(User $user): array
    {
        return [
            'statistics' => [
                'total' => $this->base($user)->count(),
                'pending' => $this->base($user)->pending()->count(),
                'in_progress' => $this->base($user)->inProgress()->count(),
                'completed' => $this->base($user)->completed()->count(),
                'overdue' => $this->base($user)->overdue()->count(),
                'complaints' => $this->base($user)->complaints()->count(),
            ],
            'recent_requests' => $this->base($user)
                ->with(['category', 'requestType', 'status', 'assignee', 'requester'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::RECENT_LIMIT)
                ->get(),
            'by_category' => $this->byCategory($user),
            'by_status' => $this->byStatus($user),
        ];
    }

    /**
     * @return Builder<HrRequest>
     */
    private function base(User $user): Builder
    {
        return $this->queries->applyStaffVisibility(HrRequest::query(), $user);
    }

    /**
     * @return array<int, int> keyed by the grouped column value
     */
    private function countsBy(User $user, string $column): array
    {
        return $this->base($user)
            ->select($column, DB::raw('count(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byCategory(User $user): array
    {
        $counts = $this->countsBy($user, 'hr_category_id');

        return HrCategory::query()
            ->ordered()
            ->get(['id', 'slug', 'name', 'is_confidential'])
            ->filter(fn (HrCategory $category) => $user->canViewConfidentialHr() || ! $category->is_confidential
                || ($counts[$category->id] ?? 0) > 0)
            ->map(fn (HrCategory $category) => [
                'id' => (int) $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'count' => $counts[$category->id] ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byStatus(User $user): array
    {
        $counts = $this->countsBy($user, 'status_id');

        return HrRequestStatus::query()
            ->ordered()
            ->get(['id', 'slug', 'name', 'color_token'])
            ->map(fn (HrRequestStatus $status) => [
                'id' => (int) $status->id,
                'slug' => $status->slug,
                'name' => $status->name,
                'color_token' => $status->color_token,
                'count' => $counts[$status->id] ?? 0,
            ])
            ->values()
            ->all();
    }
}
