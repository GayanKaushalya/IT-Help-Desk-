<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['hr_requests', 'adm_requests'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'contact_phone')) {
                    $table->string('contact_phone', 40)->nullable();
                }

                if (! Schema::hasColumn($name, 'verification_due_at')) {
                    $table->timestamp('verification_due_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'verification_due_at')) {
                    $table->dropColumn('verification_due_at');
                }

                if (Schema::hasColumn($name, 'contact_phone')) {
                    $table->dropColumn('contact_phone');
                }
            });
        }
    }
};
