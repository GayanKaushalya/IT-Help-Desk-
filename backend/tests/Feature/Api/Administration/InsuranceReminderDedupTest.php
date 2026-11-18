<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Administration;

use App\Enums\RoleSlug;
use App\Models\InsurancePolicy;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceReminderDedupTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->officer = User::factory()->role(RoleSlug::InsuranceOfficer)->create();
        $this->manager = User::factory()->role(RoleSlug::AdministrationManager)->create();
    }

    private function policy(string $number, int $daysAhead, array $extra = []): InsurancePolicy
    {
        return InsurancePolicy::query()->create(array_merge([
            'policy_number' => $number,
            'provider' => 'Ceylinco',
            'insurance_type' => 'Motor',
            'expiry_date' => now()->addDays($daysAhead)->toDateString(),
            'responsible_officer_id' => $this->officer->id,
        ], $extra));
    }

    public function test_running_the_command_twice_sends_each_reminder_once(): void
    {
        $due = $this->policy('POL-7', 7);
        $this->policy('POL-20', 20); // not on a reminder threshold

        $this->artisan('adm:insurance-reminders')->assertSuccessful();

        $this->assertSame(1, UserNotification::query()->where('type', 'adm.insurance.expiring')->count());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->officer->id, 'type' => 'adm.insurance.expiring']);
        $this->assertTrue(
            $due->reminders()->where('remind_days', 7)->whereDate('reminded_on', now()->toDateString())->exists(),
        );

        // Second run the same day: nothing new.
        $this->artisan('adm:insurance-reminders')->assertSuccessful();

        $this->assertSame(1, UserNotification::query()->where('type', 'adm.insurance.expiring')->count());
        $this->assertSame(1, $due->reminders()->count());
    }

    public function test_the_same_day_second_service_call_reports_skipped(): void
    {
        $this->policy('POL-1', 1);

        $service = app(\App\Services\Administration\InsuranceReminderService::class);

        $this->assertSame(['examined' => 1, 'reminded' => 1, 'skipped' => 0], $service->send());
        $this->assertSame(['examined' => 1, 'reminded' => 0, 'skipped' => 1], $service->send());
    }

    public function test_a_new_day_sends_a_new_reminder_for_the_next_threshold(): void
    {
        $policy = $this->policy('POL-14', 14);
        $service = app(\App\Services\Administration\InsuranceReminderService::class);

        $service->send();
        // A week later the policy is 7 days out: a different remind_days row, so it fires again.
        $service->send(now()->addDays(7));

        $this->assertSame(2, $policy->reminders()->count());
        $this->assertSame(2, UserNotification::query()->where('type', 'adm.insurance.expiring')->count());
    }

    public function test_renewed_and_cancelled_policies_are_skipped(): void
    {
        $this->policy('POL-R', 7, ['renewal_status' => 'renewed']);
        $this->policy('POL-C', 7, ['status' => 'cancelled']);

        $this->artisan('adm:insurance-reminders')->assertSuccessful();

        $this->assertSame(0, UserNotification::query()->where('type', 'adm.insurance.expiring')->count());
        $this->assertSame(0, \App\Models\InsuranceReminder::query()->count());
    }

    public function test_without_a_responsible_officer_register_managers_are_notified(): void
    {
        $this->policy('POL-N', 14, ['responsible_officer_id' => null]);

        $this->artisan('adm:insurance-reminders')->assertSuccessful();

        $recipients = UserNotification::query()->where('type', 'adm.insurance.expiring')->pluck('user_id')->all();

        $this->assertContains($this->manager->id, $recipients);
        $this->assertContains($this->officer->id, $recipients); // insurance officers manage registers too
        $this->assertCount(1, array_keys($recipients, $this->manager->id, true));
    }
}
