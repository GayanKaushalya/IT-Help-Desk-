<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\AdmRequest;

class IndexAdmRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AdmRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('administrationhelpdesk.pagination.max_per_page');

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable', 'integer', 'exists:adm_request_statuses,id'],
            'category_id' => ['nullable', 'integer', 'exists:adm_categories,id'],
            'request_type_id' => ['nullable', 'integer', 'exists:adm_request_types,id'],
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
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'request_type_id' => isset($validated['request_type_id']) ? (int) $validated['request_type_id'] : null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
