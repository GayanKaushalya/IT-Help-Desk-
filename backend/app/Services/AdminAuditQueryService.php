<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Support\AuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminAuditQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? config('helpdesk.pagination.default_per_page'));
        $perPage = max(1, min($perPage, (int) config('helpdesk.pagination.max_per_page')));

        $query = AuditLog::query()->with('user');

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

        if (filled($filters['entity_type'] ?? null)) {
            $this->applyEntityType($query, (string) $filters['entity_type']);
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

    private function applyEntityType(Builder $query, string $entityType): void
    {
        $map = AuditEvent::entityTypes();

        if (isset($map[$entityType])) {
            $query->where('auditable_type', $map[$entityType]);

            if ($entityType === 'user') {
                $query->where('event', 'like', 'user.%');
            }

            return;
        }

        if ($entityType === 'auth') {
            $query->where('event', 'like', 'auth.%');

            return;
        }

        if ($entityType === 'report') {
            $query->where('event', 'like', 'report.%');
        }
    }
}
