<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrRequest;
use RuntimeException;

/**
 * Issues unique public HR request numbers such as HR-2026-000001.
 *
 * Callers must invoke this inside a database transaction so lockForUpdate()
 * serialises concurrent creates for the same year.
 */
class HrReferenceGenerator
{
    public function next(): string
    {
        $prefix = (string) config('hrhelpdesk.request_reference.prefix');
        $padding = max(1, (int) config('hrhelpdesk.request_reference.padding'));
        $year = now()->year;
        $stem = $prefix.'-'.$year.'-';

        $latest = HrRequest::query()
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
            throw new RuntimeException('The HR request number sequence for '.$year.' is exhausted.');
        }

        return $stem.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
