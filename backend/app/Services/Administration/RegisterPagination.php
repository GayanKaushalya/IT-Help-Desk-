<?php

declare(strict_types=1);

namespace App\Services\Administration;

/**
 * Shared page-size handling for the Administration registers.
 */
final class RegisterPagination
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? config('administrationhelpdesk.pagination.default_per_page', 15));

        return max(1, min($perPage, (int) config('administrationhelpdesk.pagination.max_per_page', 100)));
    }

    /** Day bounds that compare correctly against date columns on every driver. */
    public static function dayStart(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d').' 00:00:00';
    }

    public static function dayEnd(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d').' 23:59:59';
    }

    /**
     * Escapes LIKE wildcards so searches are literal.
     */
    public static function like(string $term): string
    {
        return '%'.str_replace(['%', '_'], '', trim($term)).'%';
    }
}
