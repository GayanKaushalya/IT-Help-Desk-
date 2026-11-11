<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->boolean('requires_item_details')->default(false)->after('is_active');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('issue_type', 40)->nullable()->after('additional_details');
            $table->string('item_type', 40)->nullable()->after('issue_type');
            $table->string('item_other', 200)->nullable()->after('item_type');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['issue_type', 'item_type', 'item_other']);
        });

        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropColumn('requires_item_details');
        });
    }
};
