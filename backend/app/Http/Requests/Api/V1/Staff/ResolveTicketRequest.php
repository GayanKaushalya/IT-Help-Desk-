<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class ResolveTicketRequest extends ApiFormRequest
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
            'resolution_summary' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution_summary.required' => 'Describe how the issue was resolved.',
            'resolution_summary.min' => 'Describe how the issue was resolved.',
        ];
    }
}
