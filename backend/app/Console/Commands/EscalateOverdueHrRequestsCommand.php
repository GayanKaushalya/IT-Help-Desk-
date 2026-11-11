<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Hr\HrEscalationService;
use Illuminate\Console\Command;

class EscalateOverdueHrRequestsCommand extends Command
{
    protected $signature = 'hr:escalate-overdue';

    protected $description = 'Escalate HR requests that have passed their SLA due date to the HR manager';

    public function handle(HrEscalationService $escalations): int
    {
        $result = $escalations->escalateOverdue();

        $this->info(sprintf(
            'Examined %d overdue request(s); escalated %d.',
            $result['examined'],
            $result['escalated'],
        ));

        return self::SUCCESS;
    }
}
