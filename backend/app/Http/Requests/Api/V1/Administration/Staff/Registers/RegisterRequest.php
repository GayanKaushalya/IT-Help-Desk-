<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff\Registers;

use App\Http\Requests\Api\ApiFormRequest;

/**
 * Base for register write requests: managing registers needs
 * adm.registers.manage; POST requires fields, PATCH treats them as optional.
 */
abstract class RegisterRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('adm.registers.manage') ?? false;
    }

    /** "required" on create, "sometimes" on partial update. */
    protected function presence(): string
    {
        return $this->isMethod('POST') ? 'required' : 'sometimes';
    }

    /**
     * Trim strings and turn blanks into null so optional fields clear cleanly.
     *
     * @param  list<string>  $fields
     */
    protected function blankToNull(array $fields): void
    {
        $merge = [];

        foreach ($fields as $field) {
            if ($this->has($field) && (is_string($this->input($field)) || $this->input($field) === null)) {
                $value = trim((string) $this->input($field));
                $merge[$field] = $value === '' ? null : $value;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
