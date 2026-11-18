<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class DecideCctvRequest extends ApiFormRequest
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
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function notes(): ?string
    {
        $notes = $this->validated('decision_notes');

        return is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
    }
}
