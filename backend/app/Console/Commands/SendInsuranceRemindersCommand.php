<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Administration\InsuranceReminderService;
use Illuminate\Console\Command;

class SendInsuranceRemindersCommand extends Command
{
    protected $signature = 'adm:insurance-reminders';

    protected $description = 'Notify responsible officers (or Administration staff) about insurance policies nearing expiry';

    public function handle(InsuranceReminderService $reminders): int
    {
        $result = $reminders->send();

        $this->info(sprintf(
            'Examined %d policy(ies); sent %d reminder(s); skipped %d duplicate(s).',
            $result['examined'],
            $result['reminded'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
