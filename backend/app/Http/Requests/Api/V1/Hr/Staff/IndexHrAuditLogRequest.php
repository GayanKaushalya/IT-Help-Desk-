<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use App\Support\AuditEvent;
use Illuminate\Validation\Rule;

class IndexHrAuditLogRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('hr.reports.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('helpdesk.pagination.max_per_page');

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', Rule::in(array_keys(AuditEvent::hrLabels()))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'entity_type' => ['nullable', Rule::in(['hr_request', 'report'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'event' => $validated['event'] ?? null,
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'entity_type' => $validated['entity_type'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
