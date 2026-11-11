<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            // Only the hash is persisted. The plain code is shown once to the
            // issuing agent and can never be recovered from the database.
            $table->string('code_hash');
            $table->string('code_last_four', 4)->nullable();

            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('issued_to')->constrained('users');

            $table->string('status', 20)->default('pending');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_verifications');
    }
};
