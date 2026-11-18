<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateAdmSlaRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('adm.sla.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'apply_to_types' => ['sometimes', 'boolean'],
        ];
    }
}
