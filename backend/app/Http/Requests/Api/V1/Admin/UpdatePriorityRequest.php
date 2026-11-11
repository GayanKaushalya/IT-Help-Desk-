<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

class UpdatePriorityRequest extends StorePriorityRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:80'],
            'level' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'color_token' => ['sometimes', 'required', 'in:neutral,primary,success,warning,danger,info'],
            'response_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'resolution_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
