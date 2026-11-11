<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[\p{L}][\p{L}\s.\'-]{1,148}$/u'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z][A-Za-z0-9._-]{2,59}$/', 'unique:users,username'],
            'employee_id' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/_-]{1,39}$/', 'unique:users,employee_id'],
            'epf_number' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/_-]{1,39}$/', 'unique:users,epf_number'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['nullable', 'string', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
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
            'full_name' => (string) $validated['full_name'],
            'username' => (string) $validated['username'],
            'employee_id' => (string) $validated['employee_id'],
            'epf_number' => (string) $validated['epf_number'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'role_id' => (int) $validated['role_id'],
            'branch_id' => (int) $validated['branch_id'],
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            'password' => $validated['password'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }
}
