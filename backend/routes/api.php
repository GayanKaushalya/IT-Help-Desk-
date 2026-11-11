<?php

declare(strict_types=1);

use App\Enums\RoleSlug;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminBranchController;
use App\Http\Controllers\Api\V1\Admin\AdminCatalogueController;
use App\Http\Controllers\Api\V1\Admin\AdminDirectoryController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Hr\HrEmployeeDashboardController;
use App\Http\Controllers\Api\V1\Hr\HrLookupController;
use App\Http\Controllers\Api\V1\Hr\HrRequestCommentController;
use App\Http\Controllers\Api\V1\Hr\HrRequestController;
use App\Http\Controllers\Api\V1\Hr\HrStaff\HrAuditLogController;
use App\Http\Controllers\Api\V1\Hr\HrStaff\HrDashboardController;
use App\Http\Controllers\Api\V1\Hr\HrStaff\HrReportController;
use App\Http\Controllers\Api\V1\Hr\HrStaff\HrSlaController;
use App\Http\Controllers\Api\V1\Hr\HrStaff\HrStaffRequestController;
use App\Http\Controllers\Api\V1\KnowledgeArticleController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Staff\StaffAgentController;
use App\Http\Controllers\Api\V1\Staff\StaffDashboardController;
use App\Http\Controllers\Api\V1\Staff\StaffTicketController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (v1)
|--------------------------------------------------------------------------
|
| Every endpoint is versioned so future breaking changes can ship as /v2
| without disturbing existing clients.
|
| The structure below is the contract the remaining modules plug into:
|
|   public      - no authentication, heavily rate limited
|   protected   - any authenticated, active account
|   staff       - IT support area
|   admin       - super admin configuration area
|
*/

Route::prefix('v1')->as('api.v1.')->group(function (): void {

    /*
    | Public --------------------------------------------------------------
    */
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('health', HealthController::class)->name('health');
        Route::get('lookups/public', [LookupController::class, 'public'])->name('lookups.public');
    });

    /*
    | Authentication ------------------------------------------------------
    |
    | Sign-in and registration use dedicated limiters so a burst of failed
    | logins cannot lock out a legitimate registration, and vice versa.
    */
    Route::prefix('auth')->as('auth.')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth')
            ->name('login');

        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('register');

        Route::middleware('auth:sanctum')->group(function (): void {
            // Logout is deliberately outside the `active` check so a suspended
            // account can still discard its token.
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

            Route::get('me', [AuthController::class, 'me'])
                ->middleware('active')
                ->name('me');
        });
    });

    /*
    | Authenticated -------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {

        Route::get('lookups', [LookupController::class, 'index'])->name('lookups.index');

        Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

        Route::get('knowledge-articles', [KnowledgeArticleController::class, 'index'])->name('knowledge.index');
        Route::post('knowledge-articles', [KnowledgeArticleController::class, 'store'])->name('knowledge.store');
        Route::get('knowledge-articles/{knowledge_article}', [KnowledgeArticleController::class, 'show'])->name('knowledge.show');
        Route::patch('knowledge-articles/{knowledge_article}', [KnowledgeArticleController::class, 'update'])->name('knowledge.update');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
            ->name('notifications.unread-count');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::post('notifications/{user_notification}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])
            ->middleware('throttle:uploads')
            ->name('tickets.store');
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])
            ->name('tickets.comments.store');
        Route::post('tickets/{ticket}/verify', [TicketVerificationController::class, 'store'])
            ->middleware('throttle:verify')
            ->name('tickets.verify');
        Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])
            ->middleware('throttle:uploads')
            ->name('tickets.attachments.store');
        Route::get('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'show'])
            ->name('tickets.attachments.show');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

        /*
        | HR helpdesk -----------------------------------------------------
        */
        Route::prefix('hr')->as('hr.')->group(function (): void {
            Route::get('lookups', [HrLookupController::class, 'index'])->name('lookups.index');
            Route::get('dashboard', [HrEmployeeDashboardController::class, 'show'])->name('dashboard.show');

            Route::get('requests', [HrRequestController::class, 'index'])->name('requests.index');
            Route::post('requests', [HrRequestController::class, 'store'])
                ->middleware('throttle:uploads')
                ->name('requests.store');
            Route::get('requests/track/{reference}', [HrRequestController::class, 'track'])->name('requests.track');
            Route::post('requests/{hr_request}/comments', [HrRequestCommentController::class, 'store'])
                ->name('requests.comments.store');
            Route::post('requests/{hr_request}/verify', [HrRequestController::class, 'verify'])
                ->middleware('throttle:verify')
                ->name('requests.verify');
            Route::get('requests/{hr_request}/attachments/{attachment}', [HrRequestController::class, 'downloadAttachment'])
                ->whereNumber('attachment')
                ->name('requests.attachments.show');
            Route::get('requests/{hr_request}', [HrRequestController::class, 'show'])->name('requests.show');

            /*
            | HR staff area -----------------------------------------------
            */
            Route::prefix('staff')
                ->as('staff.')
                ->middleware('role:'.implode(',', RoleSlug::hrStaffSlugs()))
                ->group(function (): void {
                    Route::get('dashboard', [HrDashboardController::class, 'show'])->name('dashboard.show');
                    Route::get('agents', [HrStaffRequestController::class, 'agents'])->name('agents.index');
                    Route::get('reports', [HrReportController::class, 'show'])->name('reports.show');
                    Route::get('reports/export', [HrReportController::class, 'export'])->name('reports.export');
                    Route::get('audit-logs', [HrAuditLogController::class, 'index'])->name('audit-logs.index');
                    Route::get('audit-logs/lookups', [HrAuditLogController::class, 'lookups'])
                        ->name('audit-logs.lookups');

                    Route::get('sla', [HrSlaController::class, 'index'])->name('sla.index');
                    Route::patch('sla/categories/{category}', [HrSlaController::class, 'updateCategory'])
                        ->whereNumber('category')
                        ->name('sla.categories.update');
                    Route::patch('sla/types/{type}', [HrSlaController::class, 'updateType'])
                        ->whereNumber('type')
                        ->name('sla.types.update');

                    Route::get('requests', [HrStaffRequestController::class, 'index'])->name('requests.index');
                    Route::get('requests/assigned', [HrStaffRequestController::class, 'assigned'])->name('requests.assigned');
                    Route::get('requests/{hr_request}', [HrStaffRequestController::class, 'show'])->name('requests.show');
                    Route::post('requests/{hr_request}/assign', [HrStaffRequestController::class, 'assign'])
                        ->name('requests.assign');
                    Route::patch('requests/{hr_request}/status', [HrStaffRequestController::class, 'updateStatus'])
                        ->name('requests.status');
                    Route::post('requests/{hr_request}/resolve', [HrStaffRequestController::class, 'resolve'])
                        ->name('requests.resolve');
                    Route::post('requests/{hr_request}/close-as-verified', [HrStaffRequestController::class, 'closeAsVerified'])
                        ->name('requests.close-as-verified');
                    Route::post('requests/{hr_request}/refer', [HrStaffRequestController::class, 'refer'])
                        ->name('requests.refer');
                    Route::post('requests/{hr_request}/referrals/{referral}/respond', [HrStaffRequestController::class, 'respondToReferral'])
                        ->whereNumber('referral')
                        ->name('requests.referrals.respond');
                    Route::post('requests/{hr_request}/comments', [HrStaffRequestController::class, 'comment'])
                        ->name('requests.comments.store');
                });
        });

        /*
        | IT support area -------------------------------------------------
        */
        Route::prefix('staff')
            ->as('staff.')
            ->middleware('role:'.implode(',', RoleSlug::staffSlugs()))
            ->group(function (): void {
                Route::get('dashboard', [StaffDashboardController::class, 'show'])->name('dashboard.show');
                Route::get('agents', [StaffAgentController::class, 'index'])->name('agents.index');

                Route::get('tickets', [StaffTicketController::class, 'index'])->name('tickets.index');
                Route::get('tickets/assigned', [StaffTicketController::class, 'assigned'])->name('tickets.assigned');
                Route::post('tickets/{ticket}/assign', [StaffTicketController::class, 'assign'])->name('tickets.assign');
                Route::patch('tickets/{ticket}/status', [StaffTicketController::class, 'updateStatus'])->name('tickets.status');
                Route::patch('tickets/{ticket}/priority', [StaffTicketController::class, 'updatePriority'])->name('tickets.priority');
                Route::post('tickets/{ticket}/resolve', [StaffTicketController::class, 'resolve'])->name('tickets.resolve');
                Route::post('tickets/{ticket}/clear-resolution', [StaffTicketController::class, 'clearResolution'])
                    ->name('tickets.clear-resolution');
                Route::post('tickets/{ticket}/close-as-verified', [StaffTicketController::class, 'closeAsVerified'])
                    ->name('tickets.close-as-verified');
                Route::post('tickets/{ticket}/comments', [StaffTicketController::class, 'comment'])->name('tickets.comments.store');
                Route::post('tickets/{ticket}/attachments', [StaffTicketController::class, 'attach'])
                    ->middleware('throttle:uploads')
                    ->name('tickets.attachments.store');
                Route::get('tickets/{ticket}', [StaffTicketController::class, 'show'])->name('tickets.show');
            });

        /*
        | Administration area ---------------------------------------------
        */
        Route::prefix('admin')
            ->as('admin.')
            ->middleware('role:'.implode(',', RoleSlug::staffSlugs()))
            ->group(function (): void {
                Route::get('lookups', [AdminDirectoryController::class, 'lookups'])->name('lookups');

                Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
                Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
                Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
                Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
                Route::post('users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
                Route::post('users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
                Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
                    ->name('users.reset-password');

                Route::get('branches', [AdminBranchController::class, 'index'])->name('branches.index');
                Route::post('branches', [AdminBranchController::class, 'store'])->name('branches.store');
                Route::get('branches/{branch}', [AdminBranchController::class, 'show'])->name('branches.show');
                Route::patch('branches/{branch}', [AdminBranchController::class, 'update'])->name('branches.update');
                Route::post('branches/{branch}/activate', [AdminBranchController::class, 'activate'])->name('branches.activate');
                Route::post('branches/{branch}/deactivate', [AdminBranchController::class, 'deactivate'])
                    ->name('branches.deactivate');

                Route::get('reports', [AdminReportController::class, 'show'])->name('reports.show');
                Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');

                Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
                Route::get('audit-logs/lookups', [AdminAuditLogController::class, 'lookups'])
                    ->name('audit-logs.lookups');

                Route::get('catalogue', [AdminCatalogueController::class, 'index'])->name('catalogue.index');
                Route::post('catalogue/categories', [AdminCatalogueController::class, 'storeCategory'])
                    ->name('catalogue.categories.store');
                Route::patch('catalogue/categories/{ticket_category}', [AdminCatalogueController::class, 'updateCategory'])
                    ->name('catalogue.categories.update');
                Route::post('catalogue/priorities', [AdminCatalogueController::class, 'storePriority'])
                    ->name('catalogue.priorities.store');
                Route::patch('catalogue/priorities/{ticket_priority}', [AdminCatalogueController::class, 'updatePriority'])
                    ->name('catalogue.priorities.update');
                Route::patch('catalogue/statuses/{ticket_status}', [AdminCatalogueController::class, 'updateStatus'])
                    ->name('catalogue.statuses.update');

                Route::get('settings', [AdminSettingController::class, 'show'])->name('settings.show');
                Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
            });
    });
});
