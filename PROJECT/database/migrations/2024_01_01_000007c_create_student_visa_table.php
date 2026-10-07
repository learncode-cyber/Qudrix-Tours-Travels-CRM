<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 5: Student Visa — genuinely new. No model, migration, or
 * controller existed anywhere before this phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_visa_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // counselor

            // Student profile
            $table->string('student_name');
            $table->string('student_email')->nullable();
            $table->string('student_phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('passport_number')->nullable();

            // Study destination
            $table->string('destination_country');
            $table->string('university')->nullable();
            $table->string('course')->nullable();
            $table->string('intake')->nullable(); // e.g. "Fall 2027"

            // Application tracking
            $table->string('status')->default('inquiry');
            // inquiry, documents_pending, application_submitted, offer_received,
            // offer_accepted, visa_applied, visa_approved, visa_rejected, enrolled, withdrawn
            $table->date('application_deadline')->nullable();
            $table->timestamp('appointment_date')->nullable();
            $table->boolean('offer_letter_received')->default(false);
            $table->date('offer_letter_date')->nullable();
            $table->string('visa_status')->nullable(); // not_applied, applied, approved, rejected
            $table->date('visa_decision_date')->nullable();
            $table->text('counselling_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_visa_applications');
    }
};
