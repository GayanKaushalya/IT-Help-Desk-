<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\AdmRequest;
use RuntimeException;

/**
 * Issues unique public Administration request numbers such as ADM-2026-000001.
 *
 * Callers must invoke this inside a database transaction so lockForUpdate()
 * serialises concurrent creates for the same year.
 */
class AdmReferenceGenerator
{
    public function next(): string
    {
        $prefix = (string) config('administrationhelpdesk.request_reference.prefix');
        $padding = max(1, (int) config('administrationhelpdesk.request_reference.padding'));
        $year = now()->year;
        $stem = $prefix.'-'.$year.'-';

        $latest = AdmRequest::query()
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
            throw new RuntimeException('The Administration request number sequence for '.$year.' is exhausted.');
        }

        return $stem.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
