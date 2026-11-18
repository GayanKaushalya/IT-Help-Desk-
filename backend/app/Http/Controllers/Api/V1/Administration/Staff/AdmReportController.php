<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff;

use App\Http\Controllers\Controller;
use App\Services\Administration\AdmReportService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmReportController extends Controller
{
    public function __construct(private readonly AdmReportService $reports) {}

    public function show(Request $request): JsonResponse
    {
        if (! ($request->user()?->hasPermission('adm.reports.view') ?? false)) {
            throw new AuthorizationException;
        }

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category_id' => ['nullable', 'integer', 'exists:adm_categories,id'],
        ]);

        return ApiResponse::success($this->reports->build($filters));
    }
}
