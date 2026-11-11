<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\RoleSlug;
use App\Models\HrRequest;
use App\Models\HrSlaSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Escalates overdue HR requests to an HR manager when the category/type SLA
 * says escalate_to_manager. Safe to run repeatedly (already-escalated rows skip).
 */
class HrEscalationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array{examined: int, escalated: int}
     */
    public function escalateOverdue(): array
    {
        $manager = $this->resolveManager();

        if (! $manager) {
            return ['examined' => 0, 'escalated' => 0];
        }

        $examined = 0;
        $escalated = 0;

        HrRequest::query()
            ->with(['category', 'requestType', 'status', 'assignee', 'requester'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNull('escalated_at')
            ->whereHas('status', fn ($status) => $status->where('is_terminal', false))
            ->orderBy('id')
            ->each(function (HrRequest $request) use ($manager, &$examined, &$escalated): void {
                $examined++;

                if (! $this->shouldEscalate($request)) {
                    return;
                }

                $this->escalate($request, $manager);
                $escalated++;
            });

        return ['examined' => $examined, 'escalated' => $escalated];
    }

    public function escalate(HrRequest $request, User $manager): HrRequest
    {
        $updated = DB::transaction(function () use ($request, $manager) {
            $request = HrRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->loadMissing('status');

            if ($request->escalated_at !== null || ($request->status?->is_terminal ?? false)) {
                return $request;
            }

            $request->forceFill([
                'escalated_at' => now(),
                'escalated_to' => $manager->id,
            ])->save();

            $this->audit->log(
                event: 'hr_request.escalated',
                subject: $request,
                newValues: [
                    'escalated_to' => $manager->id,
                    'due_at' => $request->due_at?->toIso8601String(),
                ],
                description: $request->reference.' was escalated to '.$manager->username.' after missing its SLA',
            );

            return $request->fresh() ?? $request;
        });

        $updated->load(['requester', 'assignee', 'status', 'category', 'requestType']);
        $this->notifications->hrRequestEscalated($updated, $manager);

        return $updated;
    }

    private function shouldEscalate(HrRequest $request): bool
    {
        $typeSetting = HrSlaSetting::query()
            ->where('is_active', true)
            ->where('hr_request_type_id', $request->hr_request_type_id)
            ->first();

        if ($typeSetting) {
            return (bool) $typeSetting->escalate_to_manager;
        }

        $categorySetting = HrSlaSetting::query()
            ->where('is_active', true)
            ->where('hr_category_id', $request->hr_category_id)
            ->whereNull('hr_request_type_id')
            ->first();

        if ($categorySetting) {
            return (bool) $categorySetting->escalate_to_manager;
        }

        return true;
    }

    private function resolveManager(): ?User
    {
        return User::query()
            ->hrStaff()
            ->active()
            ->whereHas('role', fn ($role) => $role->where('slug', RoleSlug::HrManager->value))
            ->orderBy('id')
            ->first()
            ?? User::query()
                ->hrStaff()
                ->active()
                ->whereHas(
                    'role',
                    fn ($role) => $role->whereIn('slug', [
                        RoleSlug::Administrator->value,
                        RoleSlug::SuperAdmin->value,
                    ]),
                )
                ->orderBy('id')
                ->first();
    }
}
