<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr\HrStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Hr\Staff\HrReportRequest;
use App\Models\User;
use App\Services\Hr\HrReportAnalyticsService;
use App\Services\Hr\HrReportExportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportController extends Controller
{
    public function __construct(
        private readonly HrReportAnalyticsService $analytics,
        private readonly HrReportExportService $exports,
    ) {}

    public function show(HrReportRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return ApiResponse::success($this->analytics->summarise($actor, $request->filters()));
    }

    public function export(HrReportRequest $request): StreamedResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->exports->download($actor, $request->filters(), $request->exportFormat());
    }
}
