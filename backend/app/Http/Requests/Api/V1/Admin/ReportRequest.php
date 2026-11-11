<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $permission = $this->routeIs('api.v1.admin.reports.export')
            ? 'reports.export'
            : 'reports.view';

        return $this->user()?->hasPermission($permission) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status_id' => ['nullable', 'integer', 'exists:ticket_statuses,id'],
            'format' => ['sometimes', Rule::in(['csv', 'pdf'])],
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'priority_id' => isset($validated['priority_id']) ? (int) $validated['priority_id'] : null,
            'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            'status_id' => isset($validated['status_id']) ? (int) $validated['status_id'] : null,
        ];
    }

    public function exportFormat(): string
    {
        return (string) ($this->validated('format') ?? 'csv');
    }
}
