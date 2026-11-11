<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for expected, client-facing failures.
 *
 * Carries the HTTP status and the machine-readable code that the frontend
 * switches on, so controllers never have to build error responses by hand.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = Response::HTTP_BAD_REQUEST,
        public readonly string $errorCode = 'API_ERROR',
    ) {
        parent::__construct($message);
    }
}
