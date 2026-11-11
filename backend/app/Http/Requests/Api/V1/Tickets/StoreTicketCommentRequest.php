<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tickets;

use App\Http\Requests\Api\ApiFormRequest;

class StoreTicketCommentRequest extends ApiFormRequest
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
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Enter a message to send.',
            'body.min' => 'Your message must be at least 2 characters.',
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
        return (string) $this->validated()['body'];
    }
}
