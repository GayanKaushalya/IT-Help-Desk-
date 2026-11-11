<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateHrRequestStatusRequest extends ApiFormRequest
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
            'status_id' => [
                'required',
                'integer',
                Rule::exists('hr_request_statuses', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function remarks(): ?string
    {
        $remarks = $this->validated('remarks');

        return is_string($remarks) && trim($remarks) !== '' ? trim($remarks) : null;
    }
}
