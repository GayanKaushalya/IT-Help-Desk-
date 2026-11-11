<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateHrSlaRequest extends ApiFormRequest
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
            'target_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'escalate_to_manager' => ['sometimes', 'boolean'],
        ];
    }
}
