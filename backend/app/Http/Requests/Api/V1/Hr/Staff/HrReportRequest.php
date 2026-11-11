<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class HrReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $permission = $this->routeIs('api.v1.hr.staff.reports.export')
            ? 'hr.reports.export'
            : 'hr.reports.view';

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
            'category_id' => ['nullable', 'integer', 'exists:hr_categories,id'],
            'request_type_id' => ['nullable', 'integer', 'exists:hr_request_types,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status_id' => ['nullable', 'integer', 'exists:hr_request_statuses,id'],
            'confidential' => ['nullable', 'boolean'],
            'format' => ['sometimes', Rule::in(['csv', 'pdf'])],
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'request_type_id' => isset($validated['request_type_id']) ? (int) $validated['request_type_id'] : null,
            'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            'status_id' => isset($validated['status_id']) ? (int) $validated['status_id'] : null,
            'confidential' => array_key_exists('confidential', $validated)
                ? (bool) $validated['confidential']
                : null,
        ];
    }

    public function exportFormat(): string
    {
        return (string) ($this->validated('format') ?? 'csv');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('confidential')) {
            $this->merge([
                'confidential' => filter_var($this->input('confidential'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
