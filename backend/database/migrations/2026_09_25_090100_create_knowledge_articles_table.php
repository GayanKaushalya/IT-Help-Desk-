<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 180);
            $table->string('summary', 255)->nullable();
            $table->text('body');
            $table->string('topic', 80)->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('author_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'updated_at']);
            $table->index('topic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
