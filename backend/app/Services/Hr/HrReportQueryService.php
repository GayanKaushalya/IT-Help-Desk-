<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Shared HR report filters. Column names stay prefixed so joins remain valid.
 */
class HrReportQueryService
{
    public function __construct(private readonly HrRequestQueryService $visibility) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder|QueryBuilder $query, array $filters, User $actor): void
    {
        if ($query instanceof Builder) {
            $this->visibility->applyStaffVisibility($query, $actor);
        }

        foreach (['status_id', 'branch_id', 'assigned_to'] as $column) {
            if (! empty($filters[$column])) {
                $query->where('hr_requests.'.$column, (int) $filters[$column]);
            }
        }

        if (! empty($filters['category_id'])) {
            $query->where('hr_requests.hr_category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['request_type_id'])) {
            $query->where('hr_requests.hr_request_type_id', (int) $filters['request_type_id']);
        }

        if (array_key_exists('confidential', $filters) && $filters['confidential'] !== null && $filters['confidential'] !== '') {
            $query->where('hr_requests.is_confidential', (bool) $filters['confidential']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('hr_requests.created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('hr_requests.created_at', '<=', $filters['date_to'].' 23:59:59');
        }
    }
}
