<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 80);
            // Higher level means more urgent; drives default sorting of queues.
            $table->unsignedSmallInteger('level')->default(0);
            // Design-system token name (e.g. "danger"), not a raw hex value, so
            // the frontend stays within the agreed palette.
            $table->string('color_token', 30)->default('neutral');
            $table->unsignedInteger('response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_priorities');
    }
};
