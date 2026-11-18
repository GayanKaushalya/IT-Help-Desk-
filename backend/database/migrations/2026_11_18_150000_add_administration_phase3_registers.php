<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administration Phase 3: working registers (insurance, vehicles, drivers,
 * fuel cards), vehicle allocations, CCTV approvals and category-specific
 * request details.
 *
 * No cascading foreign keys: SQL Server rejects multiple cascade paths and the
 * registers are soft-deleted, so rows are never physically removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Insurance
        // -----------------------------------------------------------------

        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->renameColumn('insurer', 'provider');
            $table->renameColumn('policy_type', 'insurance_type');
            $table->renameColumn('insured_item', 'insured_asset');
        });

        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->string('company_name', 150)->nullable();
            $table->string('vehicle_registration', 40)->nullable();
            $table->string('document_disk', 40)->nullable();
            $table->string('document_path', 500)->nullable();
            $table->string('document_name', 255)->nullable();
            $table->foreignId('responsible_officer_id')->nullable()->constrained('users');
            $table->string('renewal_status', 40)->default('not_due');

            $table->index('expiry_date', 'insurance_policies_expiry_date_index');
            $table->index('renewal_status', 'insurance_policies_renewal_status_index');
        });

        Schema::create('insurance_policy_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('insurance_policies');
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('event', 60);
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['policy_id', 'changed_at']);
        });

        Schema::create('insurance_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('insurance_policies');
            $table->unsignedSmallInteger('remind_days');
            $table->date('reminded_on');
            $table->timestamps();
            $table->unique(['policy_id', 'remind_days', 'reminded_on'], 'insurance_reminders_unique');
        });

        // -----------------------------------------------------------------
        // Fleet
        // -----------------------------------------------------------------

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('company_name', 150)->nullable();
            $table->string('chassis_number', 60)->nullable();
            $table->unsignedInteger('current_mileage')->nullable();
            $table->index('company_name', 'vehicles_company_name_index');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('company_name', 150)->nullable();
            $table->string('license_class', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->index('license_expiry_date', 'drivers_license_expiry_date_index');
        });

        Schema::create('vehicle_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->foreignId('adm_request_id')->nullable()->constrained('adm_requests');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 30)->default('scheduled');
            $table->string('purpose', 255)->nullable();
            $table->string('destination', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['vehicle_id', 'start_at', 'end_at'], 'vehicle_allocations_vehicle_range_index');
            $table->index(['driver_id', 'start_at', 'end_at'], 'vehicle_allocations_driver_range_index');
            $table->index('status');
        });

        // -----------------------------------------------------------------
        // Fuel cards (the PIN is never stored; only a masked reference)
        // -----------------------------------------------------------------

        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->string('company_name', 150)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users');
            $table->string('masked_card_ref', 40)->nullable();
            $table->date('issued_at')->nullable();
        });

        Schema::create('fuel_card_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_card_id')->constrained('fuel_cards');
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('event', 60);
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['fuel_card_id', 'changed_at']);
        });

        // -----------------------------------------------------------------
        // CCTV approvals
        // -----------------------------------------------------------------

        Schema::create('cctv_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_request_id')->constrained('adm_requests');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
            $table->unique('adm_request_id');
            $table->index('status');
        });

        Schema::create('cctv_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adm_request_id')->constrained('adm_requests');
            $table->foreignId('cctv_approval_id')->nullable()->constrained('cctv_approvals');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 60);
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['adm_request_id', 'created_at']);
        });

        // -----------------------------------------------------------------
        // Category-specific request fields
        // -----------------------------------------------------------------

        if (! Schema::hasColumn('adm_requests', 'details')) {
            Schema::table('adm_requests', function (Blueprint $table) {
                $table->json('details')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('adm_requests', 'details')) {
            Schema::table('adm_requests', function (Blueprint $table) {
                $table->dropColumn('details');
            });
        }

        Schema::dropIfExists('cctv_access_logs');
        Schema::dropIfExists('cctv_approvals');
        Schema::dropIfExists('fuel_card_history');

        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['company_name', 'masked_card_ref', 'issued_at']);
        });

        Schema::dropIfExists('vehicle_allocations');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('drivers_license_expiry_date_index');
            $table->dropColumn(['company_name', 'license_class', 'email']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_company_name_index');
            $table->dropColumn(['company_name', 'chassis_number', 'current_mileage']);
        });

        Schema::dropIfExists('insurance_reminders');
        Schema::dropIfExists('insurance_policy_history');

        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropIndex('insurance_policies_expiry_date_index');
            $table->dropIndex('insurance_policies_renewal_status_index');
            $table->dropConstrainedForeignId('responsible_officer_id');
            $table->dropColumn([
                'company_name',
                'vehicle_registration',
                'document_disk',
                'document_path',
                'document_name',
                'renewal_status',
            ]);
        });

        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->renameColumn('provider', 'insurer');
            $table->renameColumn('insurance_type', 'policy_type');
            $table->renameColumn('insured_asset', 'insured_item');
        });
    }
};
