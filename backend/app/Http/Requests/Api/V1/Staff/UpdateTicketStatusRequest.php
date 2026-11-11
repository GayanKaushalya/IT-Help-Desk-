<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\TicketStatus;
use Illuminate\Validation\Validator;

class UpdateTicketStatusRequest extends ApiFormRequest
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
            'status_id' => ['required', 'integer', 'exists:ticket_statuses,id'],
            'resolution_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = TicketStatus::query()->find((int) $this->input('status_id'));

            if ($status?->slug === 'closed') {
                $validator->errors()->add(
                    'status_id',
                    'Tickets can only be closed after the employee verifies the resolution.',
                );
            }

            if ($status?->slug === 'resolved' && trim((string) $this->input('resolution_summary')) === '') {
                $validator->errors()->add(
                    'resolution_summary',
                    'Describe how the issue was resolved.',
                );
            }
        });
    }

    public function resolutionSummary(): ?string
    {
        $summary = $this->validated('resolution_summary') ?? null;

        return is_string($summary) ? trim($summary) : null;
    }
}
