<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BranchResource;
use App\Http\Resources\V1\DepartmentResource;
use App\Http\Resources\V1\TicketCategoryResource;
use App\Http\Resources\V1\TicketPriorityResource;
use App\Http\Resources\V1\TicketStatusResource;
use App\Services\LookupService;
use App\Services\SettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Reference data shared by the registration and ticket forms.
 */
class LookupController extends Controller
{
    public function __construct(
        private readonly LookupService $lookups,
        private readonly SettingService $settings,
    ) {}

    /**
     * Branches and departments only. Exposed without authentication because
     * the registration form needs them before an account exists.
     */
    public function public(): JsonResponse
    {
        $settings = $this->settings->all();

        return ApiResponse::success([
            'branches' => BranchResource::collection($this->lookups->branches()),
            'departments' => DepartmentResource::collection($this->lookups->departments()),
            'organisation_name' => $settings['organisation_name'],
            'allow_self_registration' => $settings['allow_self_registration'] === '1',
            'support_email' => $settings['support_email'],
            'support_phone' => $settings['support_phone'],
            'support_hours' => $settings['support_hours'],
        ]);
    }

    /**
     * The full catalogue required to raise and triage a ticket.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'branches' => BranchResource::collection($this->lookups->branches()),
            'departments' => DepartmentResource::collection($this->lookups->departments()),
            'categories' => TicketCategoryResource::collection($this->lookups->categories()),
            'priorities' => TicketPriorityResource::collection($this->lookups->priorities()),
            'statuses' => TicketStatusResource::collection($this->lookups->statuses()),
            'contact_methods' => config('helpdesk.contact_methods'),
            'issue_types' => config('helpdesk.issue_types'),
            'item_types' => config('helpdesk.item_types'),
            'attachments' => [
                'max_kilobytes' => (int) config('helpdesk.attachments.max_kilobytes'),
                'max_per_ticket' => (int) config('helpdesk.attachments.max_per_ticket'),
                'allowed_extensions' => config('helpdesk.attachments.allowed_extensions'),
            ],
        ]);
    }
}
