<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Knowledge;

use App\Http\Requests\Api\ApiFormRequest;

class StoreKnowledgeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('knowledge.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:4', 'max:180'],
            'summary' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:20000'],
            'topic' => ['nullable', 'string', 'max:80'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'title' => (string) $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'body' => (string) $validated['body'],
            'topic' => $validated['topic'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ];
    }
}
