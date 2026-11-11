<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users');
            // Null when the system assigns automatically from category rules.
            $table->foreignId('assigned_by')->nullable()->constrained('users');

            $table->timestamp('assigned_at');
            // Set when the ticket moves on, leaving a full ownership history.
            $table->timestamp('released_at')->nullable();
            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'assigned_at']);
            $table->index(['assigned_to', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};
