<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 80);
            $table->string('color_token', 30)->default('neutral');

            // Behavioural flags keep workflow rules in data rather than in code,
            // so administrators can add statuses without a deployment.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_open')->default(true);
            $table->boolean('is_resolved')->default(false);
            $table->boolean('is_closed')->default(false);
            // Requester must supply a verification code to leave this status.
            $table->boolean('requires_verification')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_statuses');
    }
};
