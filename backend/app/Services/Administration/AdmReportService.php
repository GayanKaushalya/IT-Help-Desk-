<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\AdmCategory;
use App\Models\AdmRequest;
use App\Models\AdmRequestStatus;
use App\Models\Driver;
use App\Models\FuelCard;
use App\Models\InsurancePolicy;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Administration reports: every figure is an aggregate query on the live tables.
 */
class AdmReportService
{
    /**
     * @param  array{date_from?: ?string, date_to?: ?string, category_id?: int|string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $from = filled($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to = filled($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to'])->endOfDay() : null;
        $categoryId = ! empty($filters['category_id']) ? (int) $filters['category_id'] : null;

        $base = fn (): Builder => $this->requests($from, $to, $categoryId);

        $total = $base()->count();
        $open = $base()->open()->count();
        $resolved = $base()->whereNotNull('resolved_at')->count();
        $closed = $base()->whereNotNull('closed_at')->count();
        $overdue = $base()->overdue()->count();

        $withinSla = $base()
            ->whereNotNull('resolved_at')
            ->whereNotNull('due_at')
            ->whereColumn('resolved_at', '<=', 'due_at')
            ->count();
        $measurable = $base()->whereNotNull('resolved_at')->whereNotNull('due_at')->count();

        return [
            'filters' => [
                'date_from' => $from?->toDateString(),
                'date_to' => $to?->toDateString(),
                'category_id' => $categoryId,
            ],
            'kpis' => [
                'total_requests' => $total,
                'open_requests' => $open,
                'resolved_requests' => $resolved,
                'closed_requests' => $closed,
                'overdue_requests' => $overdue,
                'unassigned_requests' => $base()->open()->whereNull('assigned_to')->count(),
                'sla_compliance_percent' => $measurable > 0 ? round($withinSla / $measurable * 100, 1) : null,
                'avg_resolution_hours' => $this->averageResolutionHours($base()),
                'pending_cctv_approvals' => $this->pendingCctv($from, $to),
            ],
            'by_category' => $this->byCategory($from, $to, $categoryId),
            'by_status' => $this->byStatus($from, $to, $categoryId),
            'monthly_trend' => $this->monthlyTrend($categoryId),
            'insurance' => $this->insurance(),
            'allocations' => $this->allocations($from, $to),
            'registers' => [
                'vehicles' => Vehicle::query()->count(),
                'vehicles_available' => Vehicle::query()->where('status', 'available')->count(),
                'drivers' => Driver::query()->count(),
                'drivers_active' => Driver::query()->where('status', 'active')->count(),
                'fuel_cards' => FuelCard::query()->count(),
                'fuel_cards_active' => FuelCard::query()->where('status', 'active')->count(),
                'insurance_policies' => InsurancePolicy::query()->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return Builder<AdmRequest>
     */
    private function requests(?Carbon $from, ?Carbon $to, ?int $categoryId): Builder
    {
        return AdmRequest::query()
            ->when($from, fn (Builder $q) => $q->where('adm_requests.created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('adm_requests.created_at', '<=', $to))
            ->when($categoryId, fn (Builder $q) => $q->where('adm_requests.adm_category_id', $categoryId));
    }

    /**
     * Average hours from submission to resolution, computed in the database.
     *
     * @param  Builder<AdmRequest>  $query
     */
    private function averageResolutionHours(Builder $query): ?float
    {
        $minutes = match (DB::connection()->getDriverName()) {
            'sqlsrv' => 'DATEDIFF(minute, adm_requests.created_at, adm_requests.resolved_at)',
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(MINUTE, adm_requests.created_at, adm_requests.resolved_at)',
            'pgsql' => 'EXTRACT(EPOCH FROM (adm_requests.resolved_at - adm_requests.created_at)) / 60',
            default => '(julianday(adm_requests.resolved_at) - julianday(adm_requests.created_at)) * 24 * 60',
        };

        $average = $query
            ->whereNotNull('adm_requests.resolved_at')
            ->selectRaw('AVG('.$minutes.') as avg_minutes')
            ->value('avg_minutes');

        return $average === null ? null : round(((float) $average) / 60, 1);
    }

    private function pendingCctv(?Carbon $from, ?Carbon $to): int
    {
        return DB::table('cctv_approvals')
            ->join('adm_requests', 'adm_requests.id', '=', 'cctv_approvals.adm_request_id')
            ->where('cctv_approvals.status', 'pending')
            ->whereNull('adm_requests.deleted_at')
            ->when($from, fn ($q) => $q->where('adm_requests.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('adm_requests.created_at', '<=', $to))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byCategory(?Carbon $from, ?Carbon $to, ?int $categoryId): array
    {
        $counts = $this->requests($from, $to, $categoryId)
            ->select('adm_category_id', DB::raw('count(*) as aggregate'))
            ->groupBy('adm_category_id')
            ->pluck('aggregate', 'adm_category_id');

        $open = $this->requests($from, $to, $categoryId)->open()
            ->select('adm_category_id', DB::raw('count(*) as aggregate'))
            ->groupBy('adm_category_id')
            ->pluck('aggregate', 'adm_category_id');

        return AdmCategory::query()
            ->ordered()
            ->get(['id', 'slug', 'name'])
            ->map(fn (AdmCategory $category): array => [
                'id' => (int) $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'total' => (int) ($counts[$category->id] ?? 0),
                'open' => (int) ($open[$category->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byStatus(?Carbon $from, ?Carbon $to, ?int $categoryId): array
    {
        $counts = $this->requests($from, $to, $categoryId)
            ->select('status_id', DB::raw('count(*) as aggregate'))
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        return AdmRequestStatus::query()
            ->ordered()
            ->get(['id', 'slug', 'name', 'color_token'])
            ->map(fn (AdmRequestStatus $status): array => [
                'id' => (int) $status->id,
                'slug' => $status->slug,
                'name' => $status->name,
                'color_token' => $status->color_token,
                'count' => (int) ($counts[$status->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Created vs resolved for the last six calendar months (one count each).
     *
     * @return list<array{month: string, label: string, created: int, resolved: int}>
     */
    private function monthlyTrend(?int $categoryId): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();

            $months[] = [
                'month' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'created' => $this->requests($start, $end, $categoryId)->count(),
                'resolved' => AdmRequest::query()
                    ->whereBetween('resolved_at', [$start, $end])
                    ->when($categoryId, fn (Builder $q) => $q->where('adm_category_id', $categoryId))
                    ->count(),
            ];
        }

        return $months;
    }

    /**
     * @return array<string, int>
     */
    private function insurance(): array
    {
        $today = now()->startOfDay();
        $live = fn (): Builder => InsurancePolicy::query()->needingRenewal();

        $within = fn (int $days): int => $live()
            ->whereBetween('expiry_date', [RegisterPagination::dayStart($today), RegisterPagination::dayEnd($today->copy()->addDays($days))])
            ->count();

        return [
            'total' => InsurancePolicy::query()->count(),
            'active' => InsurancePolicy::query()->where('status', 'active')->count(),
            'expired' => $live()->where('expiry_date', '<', RegisterPagination::dayStart($today))->count(),
            'expiring_30_days' => $within(30),
            'expiring_60_days' => $within(60),
            'expiring_90_days' => $within(90),
            'renewal_in_progress' => InsurancePolicy::query()->where('renewal_status', 'in_progress')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function allocations(?Carbon $from, ?Carbon $to): array
    {
        $now = now();
        $range = fn (): Builder => VehicleAllocation::query()
            ->when($from, fn (Builder $q) => $q->where('end_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('start_at', '<=', $to));

        return [
            'total' => $range()->count(),
            'in_period_active' => $range()->blocking()->count(),
            'cancelled' => $range()->where('status', 'cancelled')->count(),
            'completed' => $range()->where('status', 'completed')->count(),
            'current' => VehicleAllocation::query()->blocking()
                ->where('start_at', '<=', $now)->where('end_at', '>', $now)->count(),
            'upcoming' => VehicleAllocation::query()->blocking()->where('start_at', '>', $now)->count(),
        ];
    }
}
