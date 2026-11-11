<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\ApiFormRequest;

class LoginRequest extends ApiFormRequest
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
            // Only shape is validated here. Whether the credentials are correct
            // is decided by AuthService, which answers identically for an
            // unknown username and a wrong password.
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => trim($this->input('username'))]);
        }
    }

    public function credentials(): array
    {
        return [
            'username' => (string) $this->validated('username'),
            'password' => (string) $this->validated('password'),
            'remember' => (bool) $this->boolean('remember'),
        ];
    }
}
