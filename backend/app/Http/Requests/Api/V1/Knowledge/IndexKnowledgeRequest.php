<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Knowledge;

use App\Http\Requests\Api\ApiFormRequest;

class IndexKnowledgeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', 'string', 'max:80'],
            'published' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array{search:?string, topic:?string, published:?bool, per_page:?int}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => $validated['search'] ?? null,
            'topic' => $validated['topic'] ?? null,
            'published' => array_key_exists('published', $validated)
                ? filter_var($validated['published'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
        ];
    }
}
