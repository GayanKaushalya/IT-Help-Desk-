<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class StaffIndexTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tickets.view_any') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('unassigned')) {
            return;
        }

        $this->merge([
            'unassigned' => filter_var($this->input('unassigned'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('helpdesk.pagination.max_per_page');

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer', 'exists:ticket_statuses,id'],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'status_id' => isset($validated['status_id']) ? (int) $validated['status_id'] : null,
            'priority_id' => isset($validated['priority_id']) ? (int) $validated['priority_id'] : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            'unassigned' => (bool) ($validated['unassigned'] ?? false),
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
