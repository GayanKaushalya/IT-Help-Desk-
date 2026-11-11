<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Detailed ticket rows for report exports.
 *
 * Analytics stay aggregated in the browser; exports carry the full
 * requester / issue / resolution record for audit and offline review.
 */
class ReportTicketDetailService
{
    public function __construct(private readonly ReportQueryService $filters) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, string>>
     */
    public function rows(array $filters): Collection
    {
        $query = Ticket::query()
            ->with([
                'requester:id,full_name,username,employee_id,email',
                'assignee:id,full_name',
                'resolver:id,full_name',
                'closer:id,full_name',
                'branch:id,name,code',
                'department:id,name',
                'category:id,name',
                'priority:id,name',
                'status:id,name',
            ])
            ->orderBy('tickets.created_at')
            ->orderBy('tickets.id');

        $this->filters->apply($query, $filters);

        return $query->get()->map(fn (Ticket $ticket): array => $this->mapTicket($ticket));
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'Ticket number',
            'Created at',
            'Requester',
            'Employee ID',
            'Username',
            'Requester email',
            'Branch',
            'Department',
            'Category',
            'Priority',
            'Status',
            'Subject',
            'Description',
            'Issue type',
            'Item type',
            'Item other',
            'Asset tag',
            'Preferred contact',
            'Location / desk',
            'Additional details',
            'Assigned to',
            'Assigned at',
            'First responded at',
            'Resolution summary',
            'Resolved by',
            'Resolved at',
            'Verified at',
            'Closed by',
            'Closed at',
            'Reopened',
            'Reopen count',
            'Source',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapTicket(Ticket $ticket): array
    {
        return [
            'Ticket number' => (string) $ticket->reference,
            'Created at' => $this->date($ticket->created_at),
            'Requester' => (string) ($ticket->requester?->full_name ?? ''),
            'Employee ID' => (string) ($ticket->requester?->employee_id ?? ''),
            'Username' => (string) ($ticket->requester?->username ?? ''),
            'Requester email' => (string) ($ticket->requester?->email ?? ''),
            'Branch' => (string) ($ticket->branch?->name ?? ''),
            'Department' => (string) ($ticket->department?->name ?? ''),
            'Category' => (string) ($ticket->category?->name ?? ''),
            'Priority' => (string) ($ticket->priority?->name ?? ''),
            'Status' => (string) ($ticket->status?->name ?? ''),
            'Subject' => (string) $ticket->subject,
            'Description' => (string) $ticket->description,
            'Issue type' => $this->lookupLabel('issue_types', $ticket->issue_type),
            'Item type' => $this->lookupLabel('item_types', $ticket->item_type),
            'Item other' => (string) ($ticket->item_other ?? ''),
            'Asset tag' => (string) ($ticket->asset_tag ?? ''),
            'Preferred contact' => $this->lookupLabel('contact_methods', $ticket->preferred_contact_method),
            'Location / desk' => (string) ($ticket->contact_location ?? ''),
            'Additional details' => (string) ($ticket->additional_details ?? ''),
            'Assigned to' => (string) ($ticket->assignee?->full_name ?? ''),
            'Assigned at' => $this->date($ticket->assigned_at),
            'First responded at' => $this->date($ticket->first_responded_at),
            'Resolution summary' => (string) ($ticket->resolution_summary ?? ''),
            'Resolved by' => (string) ($ticket->resolver?->full_name ?? ''),
            'Resolved at' => $this->date($ticket->resolved_at),
            'Verified at' => $this->date($ticket->verified_at),
            'Closed by' => (string) ($ticket->closer?->full_name ?? ''),
            'Closed at' => $this->date($ticket->closed_at),
            'Reopened' => $ticket->is_reopened || ($ticket->reopen_count ?? 0) > 0 ? 'Yes' : 'No',
            'Reopen count' => (string) ($ticket->reopen_count ?? 0),
            'Source' => (string) ($ticket->source ?? ''),
        ];
    }

    private function lookupLabel(string $configKey, mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        foreach (config('helpdesk.'.$configKey, []) as $option) {
            if (($option['value'] ?? null) === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    private function date(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('Y-m-d H:i');
        }

        return '';
    }
}
