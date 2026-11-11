<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateSettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organisation_name' => ['required', 'string', 'min:2', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'support_hours' => ['nullable', 'string', 'max:120'],
            'allow_self_registration' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'organisation_name' => (string) $validated['organisation_name'],
            'support_email' => (string) ($validated['support_email'] ?? ''),
            'support_phone' => (string) ($validated['support_phone'] ?? ''),
            'support_hours' => (string) ($validated['support_hours'] ?? ''),
            'allow_self_registration' => ! empty($validated['allow_self_registration']) ? '1' : '0',
        ];
    }
}
