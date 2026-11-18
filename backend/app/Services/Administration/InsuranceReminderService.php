<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\InsurancePolicy;
use App\Models\InsuranceReminder;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Daily insurance expiry reminders.
 *
 * A policy is reminded when it is exactly N days from expiry for each N in
 * administration.insurance_reminder_days. The unique (policy, days, date) row in
 * insurance_reminders makes the job safe to run repeatedly in one day.
 */
class InsuranceReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly InsurancePolicyService $policies,
    ) {}

    /**
     * @return array{examined: int, reminded: int, skipped: int}
     */
    public function send(?\DateTimeInterface $today = null): array
    {
        $day = ($today ? \Illuminate\Support\Carbon::instance($today) : now())->startOfDay();

        /** @var list<int> $thresholds */
        $thresholds = array_values(array_unique(array_map(
            'intval',
            (array) config('administration.insurance_reminder_days', [30, 14, 7, 1]),
        )));

        $examined = 0;
        $reminded = 0;
        $skipped = 0;

        if ($thresholds === []) {
            return compact('examined', 'reminded', 'skipped');
        }

        $policies = InsurancePolicy::query()
            ->needingRenewal()
            ->with('responsibleOfficer')
            ->whereBetween('expiry_date', [
                RegisterPagination::dayStart($day->copy()->addDays(min($thresholds))),
                RegisterPagination::dayEnd($day->copy()->addDays(max($thresholds))),
            ])
            ->orderBy('expiry_date')
            ->get();

        foreach ($policies as $policy) {
            $examined++;
            $days = (int) $day->diffInDays($policy->expiry_date->copy()->startOfDay());

            if (! in_array($days, $thresholds, true)) {
                continue;
            }

            if (! $this->claim($policy, $days, $day->toDateString())) {
                $skipped++;

                continue;
            }

            $this->notify($policy, $days);
            $this->policies->record($policy, null, 'reminder_sent', 'Expiry reminder sent ('.$days.' day(s) before expiry).');
            $reminded++;
        }

        return compact('examined', 'reminded', 'skipped');
    }

    /**
     * Records the reminder; false when it was already sent today.
     */
    private function claim(InsurancePolicy $policy, int $days, string $date): bool
    {
        try {
            InsuranceReminder::query()->create([
                'policy_id' => $policy->id,
                'remind_days' => $days,
                'reminded_on' => $date,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function notify(InsurancePolicy $policy, int $days): void
    {
        $recipients = $this->recipients($policy);
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in '.$days.' days');

        $this->notifications->notifyMany(
            $recipients,
            'adm.insurance.expiring',
            'Insurance policy expiring '.$when,
            $policy->policy_number.' ('.$policy->provider.') expires on '.$policy->expiry_date->toDateString().'.',
            [
                'module' => 'administration',
                'insurance_policy_id' => (int) $policy->id,
                'policy_number' => $policy->policy_number,
                'days_remaining' => $days,
            ],
        );
    }

    /**
     * The responsible officer when set and active; otherwise everyone on the
     * Administration desk who manages registers.
     *
     * @return Collection<int, User>
     */
    private function recipients(InsurancePolicy $policy): Collection
    {
        $officer = $policy->responsibleOfficer;

        if ($officer && $officer->is_active && $officer->is_approved) {
            return collect([$officer]);
        }

        return User::query()
            ->administrationStaff()
            ->active()
            ->with('role.permissions')
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('adm.registers.manage'))
            ->values();
    }
}
