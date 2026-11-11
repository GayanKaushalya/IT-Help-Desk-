<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Branch;
use Illuminate\Validation\Rule;

class IndexBranchRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Branch::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('helpdesk.pagination.max_per_page');

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    /**
     * @return array{search: string|null, status: string|null, per_page: int|null}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'status' => $validated['status'] ?? null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
