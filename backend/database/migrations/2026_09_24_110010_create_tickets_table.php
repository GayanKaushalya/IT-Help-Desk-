<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Human-facing identifier, e.g. TKT-2026-000123.
            $table->string('reference', 30)->unique();

            $table->string('subject', 200);
            $table->text('description');

            $table->foreignId('requester_id')->constrained('users');
            // Copied from the requester at creation time so historical reporting
            // is unaffected by later transfers between branches.
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('department_id')->nullable()->constrained('departments');

            $table->foreignId('category_id')->constrained('ticket_categories');
            $table->foreignId('priority_id')->constrained('ticket_priorities');
            $table->foreignId('status_id')->constrained('ticket_statuses');

            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();

            $table->string('source', 20)->default('web');
            $table->string('contact_location', 150)->nullable();
            $table->string('asset_tag', 60)->nullable();

            // SLA and lifecycle timestamps.
            $table->timestamp('due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('resolution_summary')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->boolean('is_reopened')->default(false);
            $table->unsignedSmallInteger('reopen_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Queue screens filter on status/priority and sort by recency.
            $table->index(['status_id', 'priority_id']);
            $table->index(['assigned_to', 'status_id']);
            $table->index(['requester_id', 'created_at']);
            $table->index(['branch_id', 'status_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
