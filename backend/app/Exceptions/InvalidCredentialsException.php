<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a sign-in attempt fails.
 *
 * The message is deliberately identical whether the username does not exist or
 * the password is wrong, so the endpoint cannot be used to enumerate accounts.
 */
class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'The username or password is incorrect.',
            Response::HTTP_UNAUTHORIZED,
            'INVALID_CREDENTIALS',
        );
    }
}
