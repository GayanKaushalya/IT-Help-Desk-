<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Administration\AdmCategoryResource;
use App\Http\Resources\V1\Administration\AdmStatusResource;
use App\Models\AdmCategory;
use App\Models\AdmRequestStatus;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdmLookupController extends Controller
{
    /**
     * Form data for the Administration request screens: categories with their request
     * types, workflow statuses and upload limits.
     */
    public function index(): JsonResponse
    {
        $categories = AdmCategory::query()
            ->active()
            ->ordered()
            ->with(['requestTypes' => fn ($types) => $types->active()->ordered()])
            ->get();

        $statuses = AdmRequestStatus::query()->active()->ordered()->get();

        return ApiResponse::success([
            'categories' => AdmCategoryResource::collection($categories)->resolve(),
            'statuses' => AdmStatusResource::collection($statuses)->resolve(),
            'attachments' => [
                'max_kilobytes' => (int) config('administrationhelpdesk.attachments.max_kilobytes'),
                'max_per_request' => (int) config('administrationhelpdesk.attachments.max_per_request'),
                'allowed_extensions' => array_values((array) config('administrationhelpdesk.attachments.allowed_extensions')),
            ],
            'support_email' => (string) config('administrationhelpdesk.support.email', ''),
            'support_phone' => (string) config('administrationhelpdesk.support.phone', ''),
            'support_hours' => (string) config('administrationhelpdesk.support.hours', ''),
        ]);
    }
}
