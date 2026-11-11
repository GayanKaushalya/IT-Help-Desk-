<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketVerification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Organisation-wide helpdesk analytics.
 *
 * Every metric is aggregated in the database. The browser only receives
 * KPI totals and chart series — never the underlying ticket rows.
 */
class ReportAnalyticsService
{
    public function __construct(private readonly ReportQueryService $filters) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summarise(array $filters): array
    {
        $kpis = $this->kpis($filters);
        $range = $this->resolvedRange($filters);

        return [
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'category_id' => $filters['category_id'] ?? null,
                'priority_id' => $filters['priority_id'] ?? null,
                'assigned_to' => $filters['assigned_to'] ?? null,
                'status_id' => $filters['status_id'] ?? null,
                'granularity' => $range['granularity'],
            ],
            'kpis' => $kpis,
            'charts' => [
                'volume' => $this->volumeSeries($filters, $range),
                'by_branch' => $this->countByDimension($filters, 'branches', 'tickets.branch_id', 'branches.id', ['branches.id', 'branches.name', 'branches.code', 'branches.sort_order'], 'branches.sort_order'),
                'by_category' => $this->countByDimension($filters, 'ticket_categories', 'tickets.category_id', 'ticket_categories.id', ['ticket_categories.id', 'ticket_categories.name', 'ticket_categories.slug', 'ticket_categories.sort_order'], 'ticket_categories.sort_order'),
                'by_priority' => $this->countByDimension($filters, 'ticket_priorities', 'tickets.priority_id', 'ticket_priorities.id', ['ticket_priorities.id', 'ticket_priorities.name', 'ticket_priorities.slug', 'ticket_priorities.color_token', 'ticket_priorities.sort_order'], 'ticket_priorities.sort_order'),
                'by_status' => $this->countByDimension($filters, 'ticket_statuses', 'tickets.status_id', 'ticket_statuses.id', ['ticket_statuses.id', 'ticket_statuses.name', 'ticket_statuses.slug', 'ticket_statuses.color_token', 'ticket_statuses.sort_order'], 'ticket_statuses.sort_order'),
                'by_staff' => $this->ticketsByStaff($filters),
                'aging' => $this->openAging($filters),
                'verification' => $this->verificationBreakdown($filters),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function kpis(array $filters): array
    {
        $resolution = $this->minuteDiff('tickets.created_at', 'tickets.resolved_at');
        $response = $this->minuteDiff('tickets.created_at', 'tickets.first_responded_at');
        $age = $this->dayAge('tickets.created_at');

        $row = $this->ticketQuery($filters)
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->join('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->selectRaw(implode(', ', [
                'COUNT(*) as ticket_volume',
                'SUM(CASE WHEN ticket_statuses.is_open = 1 THEN 1 ELSE 0 END) as open_tickets',
                'SUM(CASE WHEN ticket_statuses.is_closed = 1 THEN 1 ELSE 0 END) as closed_tickets',
                'SUM(CASE WHEN tickets.is_reopened = 1 OR tickets.reopen_count > 0 THEN 1 ELSE 0 END) as reopened_tickets',
                'SUM(CASE WHEN tickets.reopen_count IS NULL THEN 0 ELSE tickets.reopen_count END) as reopen_events',
                'SUM(CASE WHEN ticket_priorities.slug = \'critical\' THEN 1 ELSE 0 END) as critical_tickets',
                'SUM(CASE WHEN ticket_priorities.slug = \'critical\' AND ticket_statuses.is_open = 1 THEN 1 ELSE 0 END) as open_critical_tickets',
                'SUM(CASE WHEN ticket_statuses.is_resolved = 1 AND ticket_statuses.requires_verification = 1 AND ticket_statuses.is_closed = 0 THEN 1 ELSE 0 END) as awaiting_verification',
                'SUM(CASE WHEN tickets.verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_tickets',
                'SUM(CASE WHEN ticket_statuses.is_open = 1 AND '.$age.' > 7 THEN 1 ELSE 0 END) as aged_open_tickets',
                'AVG(CASE WHEN tickets.resolved_at IS NOT NULL THEN CAST(('.$resolution.') AS FLOAT) END) as avg_resolution_minutes',
                'AVG(CASE WHEN tickets.first_responded_at IS NOT NULL THEN CAST(('.$response.') AS FLOAT) END) as avg_first_response_minutes',
                'SUM(CASE WHEN tickets.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_sample',
                'SUM(CASE WHEN tickets.first_responded_at IS NOT NULL THEN 1 ELSE 0 END) as response_sample',
            ]))
            ->first();

        $verification = $this->verificationTotals($filters);

        $volume = (int) ($row->ticket_volume ?? 0);
        $closed = (int) ($row->closed_tickets ?? 0);
        $verified = (int) ($row->verified_tickets ?? 0);
        $issued = (int) $verification['issued'];

        return [
            'ticket_volume' => $volume,
            'open_tickets' => (int) ($row->open_tickets ?? 0),
            'closed_tickets' => $closed,
            'reopened_tickets' => (int) ($row->reopened_tickets ?? 0),
            'reopen_events' => (int) ($row->reopen_events ?? 0),
            'critical_tickets' => (int) ($row->critical_tickets ?? 0),
            'open_critical_tickets' => (int) ($row->open_critical_tickets ?? 0),
            'aged_open_tickets' => (int) ($row->aged_open_tickets ?? 0),
            'awaiting_verification' => (int) ($row->awaiting_verification ?? 0),
            'verified_tickets' => $verified,
            'average_resolution' => $this->durationMetric(
                $row->avg_resolution_minutes ?? null,
                (int) ($row->resolved_sample ?? 0),
            ),
            'average_first_response' => $this->durationMetric(
                $row->avg_first_response_minutes ?? null,
                (int) ($row->response_sample ?? 0),
            ),
            'verification' => [
                'issued' => $issued,
                'verified' => (int) $verification['verified'],
                'pending' => (int) $verification['pending'],
                'expired' => (int) $verification['expired'],
                'cancelled' => (int) $verification['cancelled'],
                'completion_rate' => $issued > 0
                    ? round(((int) $verification['verified'] / $issued) * 100, 1)
                    : 0.0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, granularity: string}  $range
     * @return list<array{date: string, created: int, closed: int}>
     */
    private function volumeSeries(array $filters, array $range): array
    {
        $createdExpr = $this->periodExpression('tickets.created_at', $range['granularity']);
        $closedExpr = $this->periodExpression('tickets.closed_at', $range['granularity']);

        $created = $this->ticketQuery($filters)
            ->selectRaw($createdExpr.' as period, COUNT(*) as aggregate')
            ->groupBy(DB::raw($createdExpr))
            ->pluck('aggregate', 'period');

        $closed = $this->ticketQuery($filters)
            ->whereNotNull('tickets.closed_at')
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

        $rows = $this->ticketQuery($filters)
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
    private function ticketsByStaff(array $filters): array
    {
        $rows = $this->ticketQuery($filters)
            ->leftJoin('users', 'tickets.assigned_to', '=', 'users.id')
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
    private function openAging(array $filters): array
    {
        $age = $this->dayAge('tickets.created_at');
        $bucket = 'CASE WHEN '.$age.' <= 1 THEN \'0-1 days\''
            .' WHEN '.$age.' <= 3 THEN \'2-3 days\''
            .' WHEN '.$age.' <= 7 THEN \'4-7 days\''
            .' WHEN '.$age.' <= 14 THEN \'8-14 days\''
            .' WHEN '.$age.' <= 30 THEN \'15-30 days\''
            .' ELSE \'30+ days\' END';

        $counts = $this->ticketQuery($filters)
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->where('ticket_statuses.is_open', true)
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
    private function verificationBreakdown(array $filters): array
    {
        $totals = $this->verificationTotals($filters);

        return [
            ['id' => 1, 'name' => 'Verified', 'slug' => 'verified', 'count' => (int) $totals['verified'], 'color_token' => 'success'],
            ['id' => 2, 'name' => 'Pending', 'slug' => 'pending', 'count' => (int) $totals['pending'], 'color_token' => 'warning'],
            ['id' => 3, 'name' => 'Expired', 'slug' => 'expired', 'count' => (int) $totals['expired'], 'color_token' => 'danger'],
            ['id' => 4, 'name' => 'Cancelled', 'slug' => 'cancelled', 'count' => (int) $totals['cancelled'], 'color_token' => 'neutral'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{issued: int, verified: int, pending: int, expired: int, cancelled: int}
     */
    private function verificationTotals(array $filters): array
    {
        $row = TicketVerification::query()
            ->join('tickets', 'ticket_verifications.ticket_id', '=', 'tickets.id')
            ->whereNull('tickets.deleted_at')
            ->tap(fn (Builder $query) => $this->filters->apply($query, $filters))
            ->selectRaw(implode(', ', [
                'COUNT(*) as issued',
                "SUM(CASE WHEN ticket_verifications.status = 'verified' THEN 1 ELSE 0 END) as verified",
                "SUM(CASE WHEN ticket_verifications.status = 'pending' THEN 1 ELSE 0 END) as pending",
                "SUM(CASE WHEN ticket_verifications.status = 'expired' THEN 1 ELSE 0 END) as expired",
                "SUM(CASE WHEN ticket_verifications.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled",
            ]))
            ->first();

        return [
            'issued' => (int) ($row->issued ?? 0),
            'verified' => (int) ($row->verified ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'expired' => (int) ($row->expired ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function ticketQuery(array $filters): Builder
    {
        return Ticket::query()
            ->tap(fn (Builder $query) => $this->filters->apply($query, $filters));
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
