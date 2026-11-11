<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\AuditLog;
use App\Models\HrRequest;
use App\Support\AuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * HR-only audit trail for the HR service desk monitoring screens.
 */
class HrAuditQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? config('helpdesk.pagination.default_per_page'));
        $perPage = max(1, min($perPage, (int) config('helpdesk.pagination.max_per_page')));

        $query = AuditLog::query()
            ->with('user')
            ->where(function (Builder $inner): void {
                $inner->where('event', 'like', 'hr.%')
                    ->orWhere('event', 'like', 'hr_request.%')
                    ->orWhere('auditable_type', HrRequest::class);
            });

        if (filled($filters['search'] ?? null)) {
            $term = str_replace(['%', '_'], '', (string) $filters['search']);

            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('event', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('ip_address', 'like', $like);
                });
            }
        }

        if (filled($filters['event'] ?? null)) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (($filters['entity_type'] ?? null) === 'hr_request') {
            $query->where(function (Builder $inner): void {
                $inner->where('auditable_type', HrRequest::class)
                    ->orWhere('event', 'like', 'hr_request.%');
            });
        }

        if (($filters['entity_type'] ?? null) === 'report') {
            $query->where('event', 'like', 'hr.report.%');
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{events: list<array{value: string, label: string}>, entity_types: list<array{value: string, label: string}>}
     */
    public function lookups(): array
    {
        return [
            'events' => AuditEvent::hrOptions(),
            'entity_types' => [
                ['value' => 'hr_request', 'label' => 'HR request'],
                ['value' => 'report', 'label' => 'Report'],
            ],
        ];
    }
}
