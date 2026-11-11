<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Branch;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Branch::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'code' => [
                'required',
                'string',
                'min:2',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{1,19}$/',
                Rule::unique('branches', 'code'),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A branch with this code already exists.',
            'code.regex' => 'Branch code may contain letters, numbers, hyphens and underscores.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['name', 'code', 'address', 'city', 'phone'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $merge[$field] = $value === '' ? null : $value;
            }
        }

        if (isset($merge['code'])) {
            $merge['code'] = mb_strtoupper($merge['code']);
        }

        if ($this->exists('is_active')) {
            $merge['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'code' => (string) $validated['code'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ];
    }
}
