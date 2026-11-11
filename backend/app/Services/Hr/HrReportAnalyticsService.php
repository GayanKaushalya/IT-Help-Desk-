<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrDepartmentReferral;
use App\Models\HrRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Organisation-wide HR helpdesk analytics.
 *
 * Metrics stay aggregated in SQL. Confidential visibility follows the
 * viewer's clearance, matching the HR staff queue.
 */
class HrReportAnalyticsService
{
    public function __construct(private readonly HrReportQueryService $filters) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summarise(User $actor, array $filters): array
    {
        $range = $this->resolvedRange($filters);

        return [
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'category_id' => $filters['category_id'] ?? null,
                'request_type_id' => $filters['request_type_id'] ?? null,
                'assigned_to' => $filters['assigned_to'] ?? null,
                'status_id' => $filters['status_id'] ?? null,
                'confidential' => $filters['confidential'] ?? null,
                'granularity' => $range['granularity'],
            ],
            'kpis' => $this->kpis($actor, $filters),
            'charts' => [
                'volume' => $this->volumeSeries($actor, $filters, $range),
                'complaint_trends' => $this->complaintTrends($actor, $filters, $range),
                'by_branch' => $this->countByDimension(
                    $actor,
                    $filters,
                    'branches',
                    'hr_requests.branch_id',
                    'branches.id',
                    ['branches.id', 'branches.name', 'branches.code', 'branches.sort_order'],
                    'branches.sort_order',
                ),
                'by_category' => $this->countByDimension(
                    $actor,
                    $filters,
                    'hr_categories',
                    'hr_requests.hr_category_id',
                    'hr_categories.id',
                    ['hr_categories.id', 'hr_categories.name', 'hr_categories.slug', 'hr_categories.sort_order'],
                    'hr_categories.sort_order',
                ),
                'by_request_type' => $this->countByDimension(
                    $actor,
                    $filters,
                    'hr_request_types',
                    'hr_requests.hr_request_type_id',
                    'hr_request_types.id',
                    ['hr_request_types.id', 'hr_request_types.name', 'hr_request_types.slug', 'hr_request_types.sort_order'],
                    'hr_request_types.sort_order',
                ),
                'by_status' => $this->countByDimension(
                    $actor,
                    $filters,
                    'hr_request_statuses',
                    'hr_requests.status_id',
                    'hr_request_statuses.id',
                    ['hr_request_statuses.id', 'hr_request_statuses.name', 'hr_request_statuses.slug', 'hr_request_statuses.color_token', 'hr_request_statuses.sort_order'],
                    'hr_request_statuses.sort_order',
                ),
                'by_staff' => $this->requestsByStaff($actor, $filters),
                'aging' => $this->openAging($actor, $filters),
                'referrals' => $this->referralBreakdown($actor, $filters),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function kpis(User $actor, array $filters): array
    {
        $resolution = $this->minuteDiff('hr_requests.created_at', 'hr_requests.resolved_at');
        $response = $this->minuteDiff('hr_requests.created_at', 'hr_requests.assigned_at');
        $age = $this->dayAge('hr_requests.created_at');
        $nowExpr = match (DB::getDriverName()) {
            'sqlsrv' => 'GETDATE()',
            'sqlite' => "datetime('now')",
            default => 'NOW()',
        };

        $row = $this->requestQuery($actor, $filters)
            ->join('hr_request_statuses', 'hr_requests.status_id', '=', 'hr_request_statuses.id')
            ->selectRaw(implode(', ', [
                'COUNT(*) as request_volume',
                'SUM(CASE WHEN hr_request_statuses.is_open = 1 THEN 1 ELSE 0 END) as open_requests',
                'SUM(CASE WHEN hr_request_statuses.is_terminal = 1 THEN 1 ELSE 0 END) as completed_requests',
                'SUM(CASE WHEN hr_requests.is_confidential = 1 THEN 1 ELSE 0 END) as confidential_requests',
                'SUM(CASE WHEN hr_requests.due_at IS NOT NULL AND hr_requests.due_at < '.$nowExpr.' AND hr_request_statuses.is_terminal = 0 THEN 1 ELSE 0 END) as overdue_requests',
                'SUM(CASE WHEN hr_request_statuses.is_open = 1 AND '.$age.' > 7 THEN 1 ELSE 0 END) as aged_open_requests',
                'AVG(CASE WHEN hr_requests.resolved_at IS NOT NULL THEN CAST(('.$resolution.') AS FLOAT) END) as avg_resolution_minutes',
                'AVG(CASE WHEN hr_requests.assigned_at IS NOT NULL THEN CAST(('.$response.') AS FLOAT) END) as avg_first_response_minutes',
                'SUM(CASE WHEN hr_requests.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_sample',
                'SUM(CASE WHEN hr_requests.assigned_at IS NOT NULL THEN 1 ELSE 0 END) as response_sample',
            ]))
            ->first();

        $complaints = (int) $this->requestQuery($actor, $filters)
            ->whereHas('category', fn (Builder $category) => $category->where('slug', 'employee-complaints'))
            ->count();

        $referrals = $this->referralTotals($actor, $filters);
        $issued = (int) $referrals['total'];

        return [
            'request_volume' => (int) ($row->request_volume ?? 0),
            'open_requests' => (int) ($row->open_requests ?? 0),
            'completed_requests' => (int) ($row->completed_requests ?? 0),
            'overdue_requests' => (int) ($row->overdue_requests ?? 0),
            'aged_open_requests' => (int) ($row->aged_open_requests ?? 0),
            'complaints' => $complaints,
            'confidential_requests' => (int) ($row->confidential_requests ?? 0),
            'average_resolution' => $this->durationMetric(
                $row->avg_resolution_minutes ?? null,
                (int) ($row->resolved_sample ?? 0),
            ),
            'average_first_response' => $this->durationMetric(
                $row->avg_first_response_minutes ?? null,
                (int) ($row->response_sample ?? 0),
            ),
            'referrals' => [
                'total' => $issued,
                'pending' => (int) $referrals['pending'],
                'completed' => (int) $referrals['completed'],
                'declined' => (int) $referrals['declined'],
                'completion_rate' => $issued > 0
                    ? round(((int) $referrals['completed'] / $issued) * 100, 1)
                    : 0.0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, granularity: string}  $range
     * @return list<array{date: string, created: int, closed: int}>
     */
    private function volumeSeries(User $actor, array $filters, array $range): array
    {
        $createdExpr = $this->periodExpression('hr_requests.created_at', $range['granularity']);
        $closedExpr = $this->periodExpression(
            'COALESCE(hr_requests.closed_at, hr_requests.resolved_at)',
            $range['granularity'],
        );

        $created = $this->requestQuery($actor, $filters)
            ->selectRaw($createdExpr.' as period, COUNT(*) as aggregate')
            ->groupBy(DB::raw($createdExpr))
            ->pluck('aggregate', 'period');

        $closed = $this->requestQuery($actor, $filters)
            ->where(function (Builder $query): void {
                $query->whereNotNull('hr_requests.closed_at')
                    ->orWhereNotNull('hr_requests.resolved_at');
            })
            ->selectRaw($closedExpr.' as period, COUNT(*) as aggregate')
            ->groupBy(DB::raw($closedExpr))
            ->pluck('aggregate', 'period');

        $cursor = $this->periodStart($range['from'], $range['granularity']);
        $end = $this->periodStart($range['to'], $range['granularity']);
        $series = [];

        while ($cursor->lte($end)) {
            $key = $this->periodKey($cursor, $range['granularity']);
            $series[] = [
                'date' => $key,
                'created' => (int) ($created[$key] ?? $created[(string) $key] ?? 0),
                'closed' => (int) ($closed[$key] ?? $closed[(string) $key] ?? 0),
            ];
            $cursor = $this->advancePeriod($cursor, $range['granularity']);
        }

        return $series;
    }

    /**
     * Month-over-month (or day/week) volume for Employee Complaints only.
     *
     * @return list<array{date: string, created: int, closed: int}>
     */
    private function complaintTrends(User $actor, array $filters, array $range): array
    {
        $complaintFilters = $filters;
        unset($complaintFilters['category_id'], $complaintFilters['request_type_id']);

        $createdExpr = $this->periodExpression('hr_requests.created_at', $range['granularity']);
        $closedExpr = $this->periodExpression(
            'COALESCE(hr_requests.closed_at, hr_requests.resolved_at)',
            $range['granularity'],
        );

        $base = $this->requestQuery($actor, $complaintFilters)
            ->whereHas('category', fn (Builder $category) => $category->where('slug', 'employee-complaints'));

        $created = (clone $base)
            ->selectRaw($createdExpr.' as period, COUNT(*) as aggregate')
            ->groupBy(DB::raw($createdExpr))
            ->pluck('aggregate', 'period');

        $closed = (clone $base)
            ->where(function (Builder $query): void {
                $query->whereNotNull('hr_requests.closed_at')
                    ->orWhereNotNull('hr_requests.resolved_at');
            })
            ->selectRaw($closedExpr.' as period, COUNT(*) as aggregate')
            ->groupBy(DB::raw($closedExpr))
            ->pluck('aggregate', 'period');

        $cursor = $this->periodStart($range['from'], $range['granularity']);
        $end = $this->periodStart($range['to'], $range['granularity']);
        $series = [];

        while ($cursor->lte($end)) {
            $key = $this->periodKey($cursor, $range['granularity']);
            $series[] = [
                'date' => $key,
                'created' => (int) ($created[$key] ?? $created[(string) $key] ?? 0),
                'closed' => (int) ($closed[$key] ?? $closed[(string) $key] ?? 0),
            ];
            $cursor = $this->advancePeriod($cursor, $range['granularity']);
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $groupColumns
     * @return list<array{id: int, name: string, slug: string|null, count: int, color_token: string|null}>
     */
    private function countByDimension(
        User $actor,
        array $filters,
        string $table,
        string $foreignKey,
        string $primaryKey,
        array $groupColumns,
        string $orderColumn,
    ): array {
        $nameColumn = $table.'.name';
        $slugColumn = in_array($table.'.slug', $groupColumns, true) ? $table.'.slug'
            : (in_array($table.'.code', $groupColumns, true) ? $table.'.code' : 'NULL');
        $colorColumn = in_array($table.'.color_token', $groupColumns, true)
            ? $table.'.color_token'
            : 'NULL';

        $rows = $this->requestQuery($actor, $filters)
            ->join($table, $foreignKey, '=', $primaryKey)
            ->selectRaw(implode(', ', [
                $primaryKey.' as id',
                $nameColumn.' as name',
                $slugColumn.' as slug',
                $colorColumn.' as color_token',
                'COUNT(*) as aggregate',
            ]))
            ->groupBy($groupColumns)
            ->orderBy($orderColumn)
            ->orderBy($nameColumn)
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'slug' => $row->slug !== null ? (string) $row->slug : null,
            'count' => (int) $row->aggregate,
            'color_token' => $row->color_token !== null ? (string) $row->color_token : null,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, name: string, slug: string|null, count: int, color_token: string|null}>
     */
    private function requestsByStaff(User $actor, array $filters): array
    {
        $rows = $this->requestQuery($actor, $filters)
            ->leftJoin('users', 'hr_requests.assigned_to', '=', 'users.id')
            ->selectRaw("COALESCE(users.id, 0) as id, COALESCE(users.full_name, 'Unassigned') as name, COUNT(*) as aggregate")
            ->groupBy('users.id', 'users.full_name')
            ->orderByDesc('aggregate')
            ->orderBy('users.full_name')
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'slug' => (int) $row->id === 0 ? 'unassigned' : null,
            'count' => (int) $row->aggregate,
            'color_token' => (int) $row->id === 0 ? 'neutral' : 'primary',
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, name: string, slug: string|null, count: int, color_token: string|null}>
     */
    private function openAging(User $actor, array $filters): array
    {
        $age = $this->dayAge('hr_requests.created_at');
        $bucket = 'CASE WHEN '.$age.' <= 1 THEN \'0-1 days\''
            .' WHEN '.$age.' <= 3 THEN \'2-3 days\''
            .' WHEN '.$age.' <= 7 THEN \'4-7 days\''
            .' WHEN '.$age.' <= 14 THEN \'8-14 days\''
            .' WHEN '.$age.' <= 30 THEN \'15-30 days\''
            .' ELSE \'30+ days\' END';

        $counts = $this->requestQuery($actor, $filters)
            ->join('hr_request_statuses', 'hr_requests.status_id', '=', 'hr_request_statuses.id')
            ->where('hr_request_statuses.is_open', true)
            ->selectRaw($bucket.' as bucket, COUNT(*) as aggregate')
            ->groupBy(DB::raw($bucket))
            ->pluck('aggregate', 'bucket');

        $order = [
            '0-1 days' => ['slug' => '0-1', 'color' => 'success'],
            '2-3 days' => ['slug' => '2-3', 'color' => 'info'],
            '4-7 days' => ['slug' => '4-7', 'color' => 'primary'],
            '8-14 days' => ['slug' => '8-14', 'color' => 'warning'],
            '15-30 days' => ['slug' => '15-30', 'color' => 'warning'],
            '30+ days' => ['slug' => '30-plus', 'color' => 'danger'],
        ];

        $series = [];
        $index = 1;

        foreach ($order as $name => $meta) {
            $series[] = [
                'id' => $index,
                'name' => $name,
                'slug' => $meta['slug'],
                'count' => (int) ($counts[$name] ?? 0),
                'color_token' => $meta['color'],
            ];
            $index++;
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, name: string, slug: string|null, count: int, color_token: string|null}>
     */
    private function referralBreakdown(User $actor, array $filters): array
    {
        $totals = $this->referralTotals($actor, $filters);

        return [
            ['id' => 1, 'name' => 'Pending', 'slug' => 'pending', 'count' => (int) $totals['pending'], 'color_token' => 'warning'],
            ['id' => 2, 'name' => 'Completed', 'slug' => 'completed', 'count' => (int) $totals['completed'], 'color_token' => 'success'],
            ['id' => 3, 'name' => 'Declined', 'slug' => 'declined', 'count' => (int) $totals['declined'], 'color_token' => 'danger'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, pending: int, completed: int, declined: int}
     */
    private function referralTotals(User $actor, array $filters): array
    {
        $row = HrDepartmentReferral::query()
            ->join('hr_requests', 'hr_department_referrals.hr_request_id', '=', 'hr_requests.id')
            ->whereNull('hr_requests.deleted_at')
            ->tap(fn (Builder $query) => $this->filters->apply($query, $filters, $actor))
            ->selectRaw(implode(', ', [
                'COUNT(*) as total',
                "SUM(CASE WHEN hr_department_referrals.status = 'pending' THEN 1 ELSE 0 END) as pending",
                "SUM(CASE WHEN hr_department_referrals.status = 'responded' THEN 1 ELSE 0 END) as completed",
                "SUM(CASE WHEN hr_department_referrals.status NOT IN ('pending', 'responded') THEN 1 ELSE 0 END) as declined",
            ]))
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'declined' => (int) ($row->declined ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function requestQuery(User $actor, array $filters): Builder
    {
        return HrRequest::query()
            ->tap(fn (Builder $query) => $this->filters->apply($query, $filters, $actor));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: CarbonImmutable, to: CarbonImmutable, granularity: string}
     */
    private function resolvedRange(array $filters): array
    {
        $to = filled($filters['date_to'] ?? null)
            ? CarbonImmutable::parse((string) $filters['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $from = filled($filters['date_from'] ?? null)
            ? CarbonImmutable::parse((string) $filters['date_from'])->startOfDay()
            : $to->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $days = $from->diffInDays($to) + 1;
        $granularity = $days <= 31 ? 'day' : ($days <= 180 ? 'week' : 'month');

        return [
            'from' => $from,
            'to' => $to,
            'granularity' => $granularity,
        ];
    }

    private function periodExpression(string $column, string $granularity): string
    {
        $driver = DB::getDriverName();

        return match ($granularity) {
            'week' => match ($driver) {
                'sqlsrv' => 'CONVERT(varchar(10), DATEADD(day, -(DATEPART(weekday, '.$column.') + @@DATEFIRST - 2) % 7, '.$column.'), 23)',
                'sqlite' => "date(".$column.", 'weekday 1', '-7 days', '+7 days')",
                default => 'DATE(DATE_SUB('.$column.', INTERVAL WEEKDAY('.$column.') DAY))',
            },
            'month' => match ($driver) {
                'sqlsrv' => 'CONVERT(varchar(7), '.$column.', 23)',
                'sqlite' => "strftime('%Y-%m', ".$column.')',
                default => "DATE_FORMAT(".$column.", '%Y-%m')",
            },
            default => match ($driver) {
                'sqlsrv' => 'CONVERT(varchar(10), '.$column.', 23)',
                'sqlite' => 'date('.$column.')',
                default => 'DATE('.$column.')',
            },
        };
    }

    private function minuteDiff(string $start, string $end): string
    {
        return match (DB::getDriverName()) {
            'sqlsrv' => 'DATEDIFF(minute, '.$start.', '.$end.')',
            'sqlite' => '(julianday('.$end.') - julianday('.$start.')) * 1440',
            default => 'TIMESTAMPDIFF(MINUTE, '.$start.', '.$end.')',
        };
    }

    private function dayAge(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlsrv' => 'DATEDIFF(day, '.$column.', GETDATE())',
            'sqlite' => "CAST(julianday('now') - julianday(".$column.') AS INTEGER)',
            default => 'DATEDIFF(CURRENT_DATE, DATE('.$column.'))',
        };
    }

    private function periodStart(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'week' => $date->startOfWeek(CarbonImmutable::MONDAY),
            'month' => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }

    private function periodKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->toDateString(),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function advancePeriod(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'week' => $date->addWeek(),
            'month' => $date->addMonth(),
            default => $date->addDay(),
        };
    }

    /**
     * @return array{minutes: int|null, label: string, sample_size: int}
     */
    private function durationMetric(mixed $minutes, int $sampleSize): array
    {
        if ($sampleSize === 0 || $minutes === null) {
            return [
                'minutes' => null,
                'label' => '—',
                'sample_size' => 0,
            ];
        }

        $rounded = (int) round((float) $minutes);

        return [
            'minutes' => $rounded,
            'label' => $this->formatMinutes($rounded),
            'sample_size' => $sampleSize,
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours < 24) {
            return $remainder === 0 ? $hours.'h' : $hours.'h '.$remainder.'m';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $remainingHours === 0 ? $days.'d' : $days.'d '.$remainingHours.'h';
    }
}
