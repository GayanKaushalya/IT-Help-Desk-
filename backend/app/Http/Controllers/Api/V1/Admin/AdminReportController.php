<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReportRequest;
use App\Models\User;
use App\Services\ReportAnalyticsService;
use App\Services\ReportExportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly ReportAnalyticsService $analytics,
        private readonly ReportExportService $exports,
    ) {}

    public function show(ReportRequest $request): JsonResponse
    {
        return ApiResponse::success($this->analytics->summarise($request->filters()));
    }

    public function export(ReportRequest $request): StreamedResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->exports->download($actor, $request->filters(), $request->exportFormat());
    }
}
