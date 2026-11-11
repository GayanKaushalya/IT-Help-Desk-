<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Knowledge;

class UpdateKnowledgeRequest extends StoreKnowledgeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:4', 'max:180'],
            'summary' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'min:10', 'max:20000'],
            'topic' => ['nullable', 'string', 'max:80'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
