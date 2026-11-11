<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // Dot-notation ability name, e.g. "tickets.assign".
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            // Grouping key used to render the permission matrix in the UI.
            $table->string('group', 50)->default('general');
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
