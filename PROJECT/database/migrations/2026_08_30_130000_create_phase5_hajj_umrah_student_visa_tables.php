<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah_packages', function (Blueprint $table) {
            $table->json('accommodations')->nullable()->after('rituals_included');
        });

        // A departure group — the operational unit a Hajj/Umrah package
        // actually runs as (a specific departure date, its own transport
        // and room allocation), as opposed to the package definition itself.
        Schema::create('hajj_umrah_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('package_type'); // hajj, umrah
            $table->unsignedBigInteger('package_id'); // hajj_packages.id or umrah_packages.id, per package_type
            $table->string('name');
            $table->date('departure_date');
            $table->date('return_date');
            $table->foreignId('group_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->unsignedInteger('capacity')->default(0);
            $table->string('status')->default('planned'); // planned, confirmed, departed, completed, cancelled
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'package_type', 'package_id']);
        });

        // A pilgrim's complete operational profile for a group departure —
        // distinct from Customer, since a booking may cover a whole family
        // and each pilgrim needs their own room/transport/visa tracking.
        Schema::create('pilgrims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hajj_umrah_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('mahram_name')->nullable();
            $table->string('room_number')->nullable();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transport_assignment')->nullable();
            $table->foreignId('visa_application_id')->nullable()->constrained('visa_applications')->nullOnDelete();
            $table->string('payment_status')->default('pending'); // pending, partial, paid
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status')->default('registered'); // registered, confirmed, travelled, completed, cancelled
            $table->timestamps();
            $table->softDeletes();
        });

        // Dedicated student visa module — distinct from the generic
        // VisaApplication, since a student visa's whole lifecycle (offer
        // letter, embassy appointment, counsellor) doesn't fit the generic
        // per-traveler visa workflow.
        Schema::create('student_visa_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_name');
            $table->date('date_of_birth')->nullable();
            $table->string('destination_country', 2);
            $table->string('university')->nullable();
            $table->string('course')->nullable();
            $table->string('intake')->nullable(); // e.g. "Fall 2027"
            $table->string('application_status')->default('inquiry');
            // inquiry, documents_pending, applied, offer_received,
            // visa_appointment_scheduled, visa_submitted, visa_approved,
            // visa_rejected, enrolled
            $table->boolean('offer_letter_received')->default(false);
            $table->date('offer_letter_date')->nullable();
            $table->timestamp('embassy_appointment_date')->nullable();
            $table->string('visa_status')->default('not_applied');
            $table->foreignId('assigned_counsellor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('service_fee', 12, 2)->nullable();
            $table->string('service_fee_currency', 3)->default('USD');
            $table->string('payment_status')->default('pending'); // pending, partial, paid
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'application_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_visa_applications');
        Schema::dropIfExists('pilgrims');
        Schema::dropIfExists('hajj_umrah_groups');

        Schema::table('umrah_packages', function (Blueprint $table) {
            $table->dropColumn('accommodations');
        });
    }
};
