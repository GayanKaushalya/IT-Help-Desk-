<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for API form requests.
 *
 * Every validation failure is returned in the standard envelope so the
 * frontend has one error shape to handle. Concrete requests only declare
 * their rules.
 */
abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::error(
                message: 'The submitted data is invalid.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: $validator->errors()->toArray(),
                code: 'VALIDATION_FAILED',
            )
        );
    }
}
