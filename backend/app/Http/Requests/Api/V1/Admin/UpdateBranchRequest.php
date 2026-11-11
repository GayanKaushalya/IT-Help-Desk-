<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Branch;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch && ($this->user()?->can('update', $branch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{1,19}$/',
                Rule::unique('branches', 'code')->ignore($branchId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
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
        $payload = [];

        foreach (['name', 'code', 'address', 'city', 'phone'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $payload['sort_order'] = (int) $validated['sort_order'];
        }

        return $payload;
    }
}
