<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4: visa_applications had no columns for embassy, appointment
 * scheduling, or staff assignment — all explicitly called for in the
 * phase spec ("Embassy", "Appointment", "Staff assignment") but never
 * implemented in Phase 0/1's original visa work. Also adding a
 * rejection_reason since approveVisa existed but rejectVisa did not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->string('embassy_name')->nullable()->after('destination_country');
            $table->timestamp('appointment_date')->nullable()->after('submission_date');
            $table->foreignId('assigned_to')->nullable()->after('agency_reference')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['embassy_name', 'appointment_date', 'assigned_to', 'rejection_reason']);
        });
    }
};
