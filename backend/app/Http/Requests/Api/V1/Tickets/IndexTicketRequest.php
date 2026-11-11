<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tickets;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Ticket;

class IndexTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    /**
     * @return array{
     *     search: string|null,
     *     status_id: int|null,
     *     priority_id: int|null,
     *     category_id: int|null,
     *     date_from: string|null,
     *     date_to: string|null,
     *     per_page: int|null
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'status_id' => isset($validated['status_id']) ? (int) $validated['status_id'] : null,
            'priority_id' => isset($validated['priority_id']) ? (int) $validated['priority_id'] : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
