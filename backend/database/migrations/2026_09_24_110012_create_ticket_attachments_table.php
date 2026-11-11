<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Deliberately not cascading: SQL Server rejects two cascade paths
            // into the same table, and the ticket cascade already covers cleanup.
            $table->foreignId('ticket_comment_id')->nullable()->constrained('ticket_comments');
            $table->foreignId('uploaded_by')->constrained('users');

            $table->string('disk', 30)->default('local');
            // Generated storage path; the original name is kept only for display.
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();

            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
