<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administration Help Desk tables (adm_*) plus the operational registers
 * (insurance policies, vehicles, drivers, fuel cards) the desk will manage.
 * Kept separate from IT tickets and HR requests so those workflows stay untouched.
 *
 * SQL Server rejects multiple cascade paths, so foreign keys that would create
 * them (users, comments, request types, registers) use NO ACTION instead of
 * SET NULL/CASCADE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adm_request_statuses', function (Blueprint $table) {
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

        Schema::create('adm_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('default_sla_hours')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('adm_request_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_category_id')->constrained('adm_categories')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->timestamps();
            $table->unique(['adm_category_id', 'slug']);
            $table->index(['adm_category_id', 'is_active', 'sort_order']);
        });

        Schema::create('adm_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->string('employee_id', 50);
            $table->string('employee_name', 150);
            $table->string('department_name', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('adm_category_id')->constrained('adm_categories');
            $table->foreignId('adm_request_type_id')->constrained('adm_request_types');
            $table->foreignId('status_id')->constrained('adm_request_statuses');
            $table->string('subject', 200);
            $table->text('description');
            $table->date('required_date')->nullable();
            $table->string('company_name', 150)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['requester_id', 'created_at']);
            $table->index(['status_id', 'assigned_to']);
            $table->index(['adm_category_id', 'status_id']);
            $table->index('due_at');
        });

        Schema::create('adm_request_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_request_id')->constrained('adm_requests')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users');
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['adm_request_id', 'released_at']);
            $table->index(['assigned_to', 'released_at']);
        });

        Schema::create('adm_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_request_id')->constrained('adm_requests')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['adm_request_id', 'created_at']);
            $table->index(['adm_request_id', 'is_internal']);
        });

        Schema::create('adm_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_request_id')->constrained('adm_requests')->cascadeOnDelete();
            $table->foreignId('adm_request_comment_id')->nullable()->constrained('adm_request_comments');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->index('adm_request_id');
        });

        Schema::create('adm_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_request_id')->constrained('adm_requests')->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('adm_request_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('adm_request_statuses');
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['adm_request_id', 'changed_at']);
        });

        Schema::create('adm_sla_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_category_id')->nullable()->constrained('adm_categories')->cascadeOnDelete();
            $table->foreignId('adm_request_type_id')->nullable()->constrained('adm_request_types');
            $table->unsignedInteger('target_hours');
            $table->boolean('escalate_to_manager')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['adm_category_id', 'adm_request_type_id']);
        });

        // -----------------------------------------------------------------
        // Registers (managed in later phases)
        // -----------------------------------------------------------------

        Schema::create('insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 80)->unique();
            $table->string('insurer', 150);
            $table->string('policy_type', 80);
            $table->string('insured_item', 200)->nullable();
            $table->decimal('premium_amount', 14, 2)->nullable();
            $table->decimal('sum_insured', 16, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'expiry_date']);
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('employee_id', 50)->nullable();
            $table->string('license_number', 80)->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 40)->unique();
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vehicle_type', 60)->nullable();
            $table->string('fuel_type', 40)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->foreignId('insurance_policy_id')->nullable()->constrained('insurance_policies');
            $table->date('revenue_license_expiry')->nullable();
            $table->string('status', 40)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('fuel_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_number', 60)->unique();
            $table->string('provider', 100)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->decimal('monthly_limit', 14, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_cards');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('insurance_policies');
        Schema::dropIfExists('adm_sla_settings');
        Schema::dropIfExists('adm_request_status_histories');
        Schema::dropIfExists('adm_request_attachments');
        Schema::dropIfExists('adm_request_comments');
        Schema::dropIfExists('adm_request_assignments');
        Schema::dropIfExists('adm_requests');
        Schema::dropIfExists('adm_request_types');
        Schema::dropIfExists('adm_categories');
        Schema::dropIfExists('adm_request_statuses');
    }
};
