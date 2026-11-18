<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff;

use App\Http\Requests\Api\ApiFormRequest;

class StoreStaffAdmCommentRequest extends ApiFormRequest
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
            'body' => ['required', 'string', 'min:2', 'max:4000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function messageBody(): string
    {
        return (string) $this->validated('body');
    }

    public function isInternal(): bool
    {
        return $this->boolean('is_internal');
    }
}
