<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Allowed IT service-desk status changes.
 *
 * Awaiting Verification is the resolved status with requires_verification,
 * not a separate slug. Closed is only reached when the employee verifies.
 *
 * @phpstan-type TransitionMap array<string, list<string>>
 */
class TicketStatusTransition
{
    /**
     * @var TransitionMap
     */
    public const MAP = [
        'new' => ['assigned', 'resolved', 'cancelled'],
        'assigned' => ['in-progress', 'on-hold', 'awaiting-user', 'resolved', 'cancelled'],
        'in-progress' => ['assigned', 'on-hold', 'awaiting-user', 'resolved', 'cancelled'],
        'on-hold' => ['assigned', 'in-progress', 'awaiting-user', 'resolved', 'cancelled'],
        'awaiting-user' => ['assigned', 'in-progress', 'on-hold', 'resolved', 'cancelled'],
        'resolved' => ['awaiting-user'],
        'closed' => [],
        'cancelled' => [],
    ];

    public function allows(string $from, string $to): bool
    {
        return in_array($to, self::MAP[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function targetsFor(string $from): array
    {
        return self::MAP[$from] ?? [];
    }
}
