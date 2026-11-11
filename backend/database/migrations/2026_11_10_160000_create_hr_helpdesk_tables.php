<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Employee Request & Complaint Management tables.
 * Kept separate from IT tickets so existing IT workflows stay untouched.
 *
 * SQL Server rejects multiple cascade paths, so foreign keys that would create
 * them (users, comments, request types) use NO ACTION instead of SET NULL/CASCADE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_request_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->string('color_token', 30)->default('neutral');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_open')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('hr_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('default_sla_hours')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('hr_request_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_category_id')->constrained('hr_categories')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->string('default_referral_department', 40)->nullable();
            $table->timestamps();
            $table->unique(['hr_category_id', 'slug']);
            $table->index(['hr_category_id', 'is_active', 'sort_order']);
        });

        Schema::create('hr_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->string('employee_id', 50);
            $table->string('employee_name', 150);
            $table->string('department_name', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('hr_category_id')->constrained('hr_categories');
            $table->foreignId('hr_request_type_id')->constrained('hr_request_types');
            $table->foreignId('status_id')->constrained('hr_request_statuses');
            $table->text('description');
            $table->boolean('is_confidential')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users');
            $table->text('resolution_summary')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['requester_id', 'created_at']);
            $table->index(['status_id', 'assigned_to']);
            $table->index(['hr_category_id', 'status_id']);
            $table->index(['is_confidential', 'status_id']);
            $table->index('due_at');
        });

        Schema::create('hr_request_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained('hr_requests')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users');
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['hr_request_id', 'released_at']);
            $table->index(['assigned_to', 'released_at']);
        });

        Schema::create('hr_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained('hr_requests')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['hr_request_id', 'created_at']);
            $table->index(['hr_request_id', 'is_internal']);
        });

        Schema::create('hr_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained('hr_requests')->cascadeOnDelete();
            $table->foreignId('hr_request_comment_id')->nullable()->constrained('hr_request_comments');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->index('hr_request_id');
        });

        Schema::create('hr_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained('hr_requests')->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('hr_request_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('hr_request_statuses');
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['hr_request_id', 'changed_at']);
        });

        Schema::create('hr_department_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained('hr_requests')->cascadeOnDelete();
            $table->string('department_slug', 40);
            $table->string('status', 40)->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->text('brief')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['hr_request_id', 'status']);
            $table->index(['department_slug', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('hr_sla_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_category_id')->nullable()->constrained('hr_categories')->cascadeOnDelete();
            $table->foreignId('hr_request_type_id')->nullable()->constrained('hr_request_types');
            $table->unsignedInteger('target_hours');
            $table->boolean('escalate_to_manager')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['hr_category_id', 'hr_request_type_id']);
        });

        Schema::create('hr_complaint_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->timestamp('granted_at');
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_complaint_access');
        Schema::dropIfExists('hr_sla_settings');
        Schema::dropIfExists('hr_department_referrals');
        Schema::dropIfExists('hr_request_status_histories');
        Schema::dropIfExists('hr_request_attachments');
        Schema::dropIfExists('hr_request_comments');
        Schema::dropIfExists('hr_request_assignments');
        Schema::dropIfExists('hr_requests');
        Schema::dropIfExists('hr_request_types');
        Schema::dropIfExists('hr_categories');
        Schema::dropIfExists('hr_request_statuses');
    }
};
