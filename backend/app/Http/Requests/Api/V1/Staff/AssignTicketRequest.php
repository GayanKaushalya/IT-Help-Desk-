<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class AssignTicketRequest extends ApiFormRequest
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
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
