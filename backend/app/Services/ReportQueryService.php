<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Shared report filters. Always prefixes ticket columns so joins stay valid.
 */
class ReportQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder|QueryBuilder $query, array $filters): void
    {
        foreach (['status_id', 'priority_id', 'category_id', 'branch_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where('tickets.'.$column, (int) $filters[$column]);
            }
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('tickets.assigned_to', (int) $filters['assigned_to']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('tickets.created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('tickets.created_at', '<=', $filters['date_to'].' 23:59:59');
        }
    }
}
