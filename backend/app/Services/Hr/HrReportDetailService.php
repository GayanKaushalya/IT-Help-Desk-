<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Detailed HR request rows for report exports.
 */
class HrReportDetailService
{
    public function __construct(private readonly HrReportQueryService $filters) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, string>>
     */
    public function rows(User $actor, array $filters): Collection
    {
        $query = HrRequest::query()
            ->with([
                'requester:id,full_name,username,employee_id,email',
                'assignee:id,full_name',
                'branch:id,name,code',
                'department:id,name',
                'category:id,name',
                'requestType:id,name',
                'status:id,name',
            ])
            ->orderBy('hr_requests.created_at')
            ->orderBy('hr_requests.id');

        $this->filters->apply($query, $filters, $actor);

        return $query->get()->map(fn (HrRequest $request): array => $this->mapRequest($request));
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'Reference',
            'Created at',
            'Requester',
            'Employee ID',
            'Employee name',
            'Username',
            'Requester email',
            'Branch',
            'Department',
            'Designation',
            'Category',
            'Request type',
            'Status',
            'Confidential',
            'Description',
            'Assigned to',
            'Assigned at',
            'Due at',
            'Resolution summary',
            'Resolved at',
            'Closed at',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapRequest(HrRequest $request): array
    {
        return [
            'Reference' => (string) $request->reference,
            'Created at' => optional($request->created_at)?->toDateTimeString() ?? '',
            'Requester' => (string) ($request->requester?->full_name ?? ''),
            'Employee ID' => (string) ($request->employee_id ?? $request->requester?->employee_id ?? ''),
            'Employee name' => (string) ($request->employee_name ?? ''),
            'Username' => (string) ($request->requester?->username ?? ''),
            'Requester email' => (string) ($request->requester?->email ?? ''),
            'Branch' => (string) ($request->branch?->name ?? ''),
            'Department' => (string) ($request->department_name ?? $request->department?->name ?? ''),
            'Designation' => (string) ($request->designation ?? ''),
            'Category' => (string) ($request->category?->name ?? ''),
            'Request type' => (string) ($request->requestType?->name ?? ''),
            'Status' => (string) ($request->status?->name ?? ''),
            'Confidential' => $request->is_confidential ? 'Yes' : 'No',
            'Description' => (string) ($request->description ?? ''),
            'Assigned to' => (string) ($request->assignee?->full_name ?? ''),
            'Assigned at' => optional($request->assigned_at)?->toDateTimeString() ?? '',
            'Due at' => optional($request->due_at)?->toDateTimeString() ?? '',
            'Resolution summary' => (string) ($request->resolution_summary ?? ''),
            'Resolved at' => optional($request->resolved_at)?->toDateTimeString() ?? '',
            'Closed at' => optional($request->closed_at)?->toDateTimeString() ?? '',
        ];
    }
}
