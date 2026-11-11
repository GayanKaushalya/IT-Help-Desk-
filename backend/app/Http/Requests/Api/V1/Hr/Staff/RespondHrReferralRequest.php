<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class RespondHrReferralRequest extends ApiFormRequest
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
            'response' => ['required', 'string', 'min:2', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('response'))) {
            $this->merge(['response' => trim($this->input('response'))]);
        }
    }

    public function responseText(): string
    {
        return (string) $this->validated('response');
    }
}
