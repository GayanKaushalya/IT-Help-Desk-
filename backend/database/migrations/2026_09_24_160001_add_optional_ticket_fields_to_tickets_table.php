<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('preferred_contact_method', 30)->nullable()->after('contact_location');
            $table->text('additional_details')->nullable()->after('preferred_contact_method');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['preferred_contact_method', 'additional_details']);
        });
    }
};
