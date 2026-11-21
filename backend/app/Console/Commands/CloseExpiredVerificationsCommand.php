<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Administration\AdmRequestService;
use App\Services\Hr\HrRequestService;
use Illuminate\Console\Command;

class CloseExpiredVerificationsCommand extends Command
{
    protected $signature = 'helpdesk:close-expired-verifications';

    protected $description = 'Close resolved HR and Administration requests the employee did not confirm within the verification window';

    public function handle(HrRequestService $hr, AdmRequestService $administration): int
    {
        $hrResult = $hr->closeExpiredVerifications();
        $admResult = $administration->closeExpiredVerifications();

        $this->info(sprintf(
            'HR: examined %d, closed %d. Administration: examined %d, closed %d.',
            $hrResult['examined'],
            $hrResult['closed'],
            $admResult['examined'],
            $admResult['closed'],
        ));

        return self::SUCCESS;
    }
}
