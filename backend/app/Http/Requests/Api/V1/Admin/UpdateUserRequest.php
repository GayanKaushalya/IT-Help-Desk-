<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'min:3', 'max:150', 'regex:/^[\p{L}][\p{L}\s.\'-]{1,148}$/u'],
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z][A-Za-z0-9._-]{2,59}$/', Rule::unique('users', 'username')->ignore($userId)],
            'employee_id' => ['sometimes', 'required', 'string', 'min:2', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/_-]{1,39}$/', Rule::unique('users', 'employee_id')->ignore($userId)],
            'epf_number' => ['sometimes', 'required', 'string', 'min:2', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/_-]{1,39}$/', Rule::unique('users', 'epf_number')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['nullable', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'is_approved' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['full_name', 'employee_id', 'epf_number', 'username', 'email', 'phone', 'job_title'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $merge[$field] = $value === '' ? null : $value;
            }
        }

        foreach (['is_active', 'is_approved'] as $field) {
            if ($this->exists($field)) {
                $merge[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
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

        foreach (['full_name', 'username', 'employee_id', 'epf_number', 'email', 'phone', 'job_title'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        foreach (['role_id', 'branch_id', 'department_id'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field] !== null ? (int) $validated[$field] : null;
            }
        }

        foreach (['is_active', 'is_approved'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = (bool) $validated[$field];
            }
        }

        if (array_key_exists('password', $validated) && is_string($validated['password']) && $validated['password'] !== '') {
            $payload['password'] = $validated['password'];
        }

        return $payload;
    }
}
