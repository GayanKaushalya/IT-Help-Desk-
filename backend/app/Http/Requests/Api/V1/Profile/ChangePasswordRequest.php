<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Profile;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{current_password: string, password: string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'current_password' => (string) $validated['current_password'],
            'password' => (string) $validated['password'],
        ];
    }
}
