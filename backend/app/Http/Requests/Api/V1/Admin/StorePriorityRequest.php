<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class StorePriorityRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('priorities.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'level' => ['required', 'integer', 'min:1', 'max:100'],
            'color_token' => ['required', 'in:neutral,primary,success,warning,danger,info'],
            'response_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'resolution_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
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
            'level' => (int) $validated['level'],
            'color_token' => (string) $validated['color_token'],
            'response_minutes' => isset($validated['response_minutes']) ? (int) $validated['response_minutes'] : null,
            'resolution_minutes' => isset($validated['resolution_minutes']) ? (int) $validated['resolution_minutes'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ];
    }
}
