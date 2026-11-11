<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
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
        $payload = [];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = (string) $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }

        if (array_key_exists('default_priority_id', $validated)) {
            $payload['default_priority_id'] = $validated['default_priority_id'] !== null
                ? (int) $validated['default_priority_id']
                : null;
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }

        if (array_key_exists('requires_item_details', $validated)) {
            $payload['requires_item_details'] = (bool) $validated['requires_item_details'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $payload['sort_order'] = (int) $validated['sort_order'];
        }

        return $payload;
    }
}
