<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * Supplies the active reference data used across the application.
 *
 * Centralising these queries keeps the "active and ordered" rule in one place
 * instead of repeating it in every controller that needs a dropdown.
 */
class LookupService
{
    public function branches(): Collection
    {
        return Branch::query()->active()->ordered()->get();
    }

    public function departments(): Collection
    {
        return Department::query()->active()->ordered()->get();
    }

    public function categories(): Collection
    {
        return TicketCategory::query()->active()->ordered()->get();
    }

    public function priorities(): Collection
    {
        return TicketPriority::query()->active()->ordered()->get();
    }

    public function statuses(): Collection
    {
        return TicketStatus::query()->active()->ordered()->get();
    }
}
