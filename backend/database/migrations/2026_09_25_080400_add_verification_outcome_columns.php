<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_verifications', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_verifications', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users');
            }

            if (! Schema::hasColumn('ticket_verifications', 'method')) {
                $table->string('method', 40)->nullable();
            }

            if (! Schema::hasColumn('ticket_verifications', 'blocked_until')) {
                $table->timestamp('blocked_until')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_verifications', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_verifications', 'verified_by')) {
                $table->dropForeign(['verified_by']);
                $table->dropColumn('verified_by');
            }

            $drop = array_values(array_filter([
                Schema::hasColumn('ticket_verifications', 'method') ? 'method' : null,
                Schema::hasColumn('ticket_verifications', 'blocked_until') ? 'blocked_until' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
