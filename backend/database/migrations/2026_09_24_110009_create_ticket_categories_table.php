<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();

            // Self-reference allows a shallow two-level catalogue without a
            // separate sub-category table.
            $table->foreignId('parent_id')->nullable()->constrained('ticket_categories');
            $table->foreignId('default_priority_id')->nullable()->constrained('ticket_priorities');
            // Team that owns this category; used later for auto-assignment.
            $table->foreignId('default_assignee_id')->nullable()->constrained('users');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
