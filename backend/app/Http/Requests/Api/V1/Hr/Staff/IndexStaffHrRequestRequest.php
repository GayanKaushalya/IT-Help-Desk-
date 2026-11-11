<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\HrRequest;

class IndexStaffHrRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewQueue', HrRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('hrhelpdesk.pagination.max_per_page');

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer', 'exists:hr_request_statuses,id'],
            'category_id' => ['nullable', 'integer', 'exists:hr_categories,id'],
            'request_type_id' => ['nullable', 'integer', 'exists:hr_request_types,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'confidential' => ['nullable', 'boolean'],
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
        $int = fn (string $key): ?int => isset($validated[$key]) ? (int) $validated[$key] : null;

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'status_id' => $int('status_id'),
            'category_id' => $int('category_id'),
            'request_type_id' => $int('request_type_id'),
            'branch_id' => $int('branch_id'),
            'assigned_to' => $int('assigned_to'),
            'unassigned' => $this->boolean('unassigned'),
            'overdue' => $this->boolean('overdue'),
            'confidential' => array_key_exists('confidential', $validated) && $validated['confidential'] !== null
                ? $this->boolean('confidential')
                : null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => $int('per_page'),
        ];
    }
}
