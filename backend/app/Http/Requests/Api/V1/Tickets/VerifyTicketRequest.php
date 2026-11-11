<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tickets;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class VerifyTicketRequest extends ApiFormRequest
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
            'decision' => ['required', 'string', Rule::in(['confirmed', 'rejected'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Choose whether the issue is resolved.',
            'decision.in' => 'Choose whether the issue is resolved.',
        ];
    }

    public function decision(): string
    {
        return (string) $this->validated('decision');
    }

    public function isConfirmed(): bool
    {
        return $this->decision() === 'confirmed';
    }
}
