<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[\p{L}][\p{L}\s.\'-]{1,148}$/u'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'employee_id' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/_-]{1,39}$/', 'unique:users,employee_id'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z][A-Za-z0-9._-]{2,59}$/', 'unique:users,username'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.regex' => 'Enter a full name using letters, spaces, hyphens or apostrophes.',
            'branch_id.required' => 'Select your branch.',
            'branch_id.exists' => 'The selected branch is not available.',
            'employee_id.unique' => 'This employee ID is already registered.',
            'employee_id.regex' => 'Employee ID may contain letters, numbers, dots, slashes, hyphens and underscores.',
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username must start with a letter and may contain letters, numbers, dots, hyphens and underscores.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['full_name', 'employee_id', 'username'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Only the fields a self-registration is allowed to set.
     *
     * Role, approval and any privilege flags are intentionally absent so they
     * cannot arrive through mass assignment even if the client sends them.
     *
     * @return array{full_name: string, branch_id: int, employee_id: string, username: string, password: string}
     */
    public function registrationData(): array
    {
        $validated = $this->validated();

        return [
            'full_name' => (string) $validated['full_name'],
            'branch_id' => (int) $validated['branch_id'],
            'employee_id' => (string) $validated['employee_id'],
            'username' => (string) $validated['username'],
            'password' => (string) $validated['password'],
        ];
    }
}
