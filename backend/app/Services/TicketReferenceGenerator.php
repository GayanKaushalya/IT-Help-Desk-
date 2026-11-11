<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use RuntimeException;

/**
 * Issues unique public ticket numbers such as IT-2026-000001.
 *
 * Callers must invoke this inside a database transaction so lockForUpdate()
 * serialises concurrent creates for the same year.
 */
class TicketReferenceGenerator
{
    public function next(): string
    {
        $prefix = (string) config('helpdesk.ticket_reference.prefix');
        $padding = max(1, (int) config('helpdesk.ticket_reference.padding'));
        $year = now()->year;
        $stem = $prefix.'-'.$year.'-';

        $latest = Ticket::query()
            ->withTrashed()
            ->where('reference', 'like', $stem.'%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        if ($sequence > (10 ** $padding) - 1) {
            throw new RuntimeException('The ticket number sequence for '.$year.' is exhausted.');
        }

        return $stem.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
