<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Identity captured during registration.
            $table->string('full_name', 150);
            $table->string('username', 60)->unique();
            $table->string('employee_id', 40)->unique();
            $table->string('epf_number', 40)->unique();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();

            // Always a bcrypt/argon hash; never written or read in plain text.
            $table->string('password');
            $table->rememberToken();

            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('department_id')->nullable()->constrained('departments');

            $table->string('job_title', 100)->nullable();
            $table->string('avatar_path', 255)->nullable();

            $table->boolean('is_active')->default(true);
            // New self-registrations wait for an administrator to approve them.
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('password_changed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'role_id']);
            $table->index('branch_id');
        });

        // SQL Server treats NULL as a value in unique constraints and therefore
        // permits only one NULL. A filtered index keeps email optional while
        // still enforcing uniqueness for the users who have one.
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE email IS NOT NULL');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 150)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
