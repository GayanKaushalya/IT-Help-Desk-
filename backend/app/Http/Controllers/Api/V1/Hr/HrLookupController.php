<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Hr\HrCategoryResource;
use App\Http\Resources\V1\Hr\HrStatusResource;
use App\Models\HrCategory;
use App\Models\HrRequestStatus;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HrLookupController extends Controller
{
    /**
     * Form data for the HR request screens: categories with their request
     * types, workflow statuses, referral departments and upload limits.
     */
    public function index(): JsonResponse
    {
        $categories = HrCategory::query()
            ->active()
            ->ordered()
            ->with(['requestTypes' => fn ($types) => $types->active()->ordered()])
            ->get();

        $statuses = HrRequestStatus::query()->active()->ordered()->get();

        return ApiResponse::success([
            'categories' => HrCategoryResource::collection($categories)->resolve(),
            'statuses' => HrStatusResource::collection($statuses)->resolve(),
            'referral_departments' => array_values((array) config('hrhelpdesk.referral_departments', [])),
            'attachments' => [
                'max_kilobytes' => (int) config('hrhelpdesk.attachments.max_kilobytes'),
                'max_per_request' => (int) config('hrhelpdesk.attachments.max_per_request'),
                'allowed_extensions' => array_values((array) config('hrhelpdesk.attachments.allowed_extensions')),
            ],
            'support_email' => (string) config('hrhelpdesk.support.email', ''),
            'support_phone' => (string) config('hrhelpdesk.support.phone', ''),
            'support_hours' => (string) config('hrhelpdesk.support.hours', ''),
        ]);
    }
}
