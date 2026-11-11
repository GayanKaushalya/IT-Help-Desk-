<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class ResolveHrRequestRequest extends ApiFormRequest
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
            'resolution_summary' => ['required', 'string', 'min:5', 'max:2000'],
            'close' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('resolution_summary'))) {
            $this->merge(['resolution_summary' => trim($this->input('resolution_summary'))]);
        }
    }

    public function summary(): string
    {
        return (string) $this->validated('resolution_summary');
    }

    public function shouldClose(): bool
    {
        return $this->boolean('close');
    }
}
