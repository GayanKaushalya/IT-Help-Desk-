<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class StoreCategoryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('categories.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'is_active' => ['nullable', 'boolean'],
            'requires_item_details' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_priority_id' => isset($validated['default_priority_id']) ? (int) $validated['default_priority_id'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'requires_item_details' => (bool) ($validated['requires_item_details'] ?? false),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ];
    }
}
