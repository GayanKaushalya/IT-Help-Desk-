<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a resolution verification code issued to a requester.
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
