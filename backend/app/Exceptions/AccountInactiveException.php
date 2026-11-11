<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when valid credentials belong to an account that may not sign in.
 *
 * Reaching this point already proves the password was correct, so naming the
 * actual reason discloses nothing further and saves a support call.
 */
class AccountInactiveException extends ApiException
{
    public static function pendingApproval(): self
    {
        return new self(
            'Your account is awaiting approval from an administrator.',
            Response::HTTP_FORBIDDEN,
            'ACCOUNT_PENDING_APPROVAL',
        );
    }

    public static function deactivated(): self
    {
        return new self(
            'This account has been deactivated. Contact the IT department.',
            Response::HTTP_FORBIDDEN,
            'ACCOUNT_DEACTIVATED',
        );
    }
}
