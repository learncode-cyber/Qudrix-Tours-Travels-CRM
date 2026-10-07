<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_PROJECT_AUDIT.md P1: Hajj/Umrah had ritual checkpoints and
 * packages, but three real operational needs were missing entirely —
 * mahram (chaperone) compliance tracking, per-room traveler assignment,
 * and installment-based payment plans (very common for Hajj/Umrah given
 * package cost). All three attach to existing entities (`booking_travelers`,
 * `hotel_bookings`, `bookings`/`payments`) rather than inventing parallel
 * structures.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A female traveler (by policy) requires a mahram. The mahram may
        // themself be a co-traveler on the same booking (mahram_traveler_id)
        // or may not be traveling at all with this group but still needs
        // recording for documentation/compliance (mahram_name/phone/passport).
        Schema::create('mahram_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_traveler_id')->constrained('booking_travelers')->cascadeOnDelete();
            $table->foreignId('mahram_traveler_id')->nullable()->constrained('booking_travelers')->nullOnDelete();
            $table->string('mahram_name')->nullable();
            $table->string('mahram_phone')->nullable();
            $table->string('mahram_passport_number')->nullable();
            $table->enum('relationship_type', ['husband', 'father', 'son', 'brother', 'grandfather', 'uncle', 'other']);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('booking_traveler_id');
        });

        // Room assignment is per hotel_booking (which already knows
        // number_of_rooms/room_type at the aggregate level) — this adds the
        // actual room-by-room, traveler-by-traveler breakdown.
        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_booking_id')->constrained('hotel_bookings')->cascadeOnDelete();
            $table->string('room_number')->nullable();
            $table->string('room_type');
            $table->unsignedInteger('max_occupancy')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('hotel_booking_id');
        });

        Schema::create('room_assignment_traveler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_assignment_id')->constrained('room_assignments')->cascadeOnDelete();
            $table->foreignId('booking_traveler_id')->constrained('booking_travelers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['room_assignment_id', 'booking_traveler_id'], 'room_traveler_unique');
        });

        // Installment plan against a booking (optionally tied to a specific
        // invoice). Individual installments link to a `payments` row only
        // once actually paid — before that, `payment_id` is null and status
        // tracks pending/overdue.
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->unsignedInteger('number_of_installments');
            $table->enum('frequency', ['monthly', 'custom'])->default('monthly');
            $table->enum('status', ['active', 'completed', 'defaulted', 'cancelled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('booking_id');
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'overdue', 'waived'])->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['installment_plan_id', 'sequence_number'], 'installment_plan_sequence_unique');
        });

        // Al-Azhar admission is currently indistinguishable from any other
        // study-abroad case on student_visa_applications. Add nullable,
        // additive fields rather than a parallel table since the existing
        // lead→document→visa-status workflow already fits Al-Azhar too.
        Schema::table('student_visa_applications', function (Blueprint $table) {
            $table->boolean('is_al_azhar')->default(false)->after('destination_country');
            $table->string('azhar_faculty')->nullable()->after('is_al_azhar');
            $table->enum('azhar_level', ['preparatory', 'bachelor', 'masters', 'phd', 'arabic_language_institute'])->nullable()->after('azhar_faculty');
            $table->string('azhar_registration_number')->nullable()->after('azhar_level');
            $table->enum('arabic_proficiency_level', ['none', 'basic', 'intermediate', 'advanced', 'native'])->nullable()->after('azhar_registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('student_visa_applications', function (Blueprint $table) {
            $table->dropColumn(['is_al_azhar', 'azhar_faculty', 'azhar_level', 'azhar_registration_number', 'arabic_proficiency_level']);
        });
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('room_assignment_traveler');
        Schema::dropIfExists('room_assignments');
        Schema::dropIfExists('mahram_relationships');
    }
};
