<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flights: GDS-ready fields (PNR, cabin class, baggage, refund
        // tracking) and a link to the supplier/vendor providing the seats,
        // so a future GDS integration has somewhere real to attach to.
        Schema::table('flights', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->string('pnr')->nullable()->after('flight_id');
            $table->string('cabin_class')->default('economy')->after('seat_number');
            $table->string('baggage_allowance')->nullable()->after('cabin_class');
            $table->string('fare_type')->nullable()->after('baggage_allowance');
            $table->string('refund_status')->nullable()->after('status');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_status');
            $table->timestamp('cancelled_at')->nullable()->after('refund_amount');
        });

        // Hotel room types: proper rate/availability granularity instead of
        // one price/room-count per property.
        Schema::create('hotel_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(2);
            $table->unsignedInteger('total_rooms')->default(0);
            $table->unsignedInteger('available_rooms')->default(0);
            $table->decimal('price_per_night', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('amenities')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hotel_extra_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->foreignId('hotel_room_type_id')->nullable()->after('hotel_id')->constrained()->nullOnDelete();
        });

        Schema::create('hotel_booking_extra_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_extra_service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2);
        });

        // Visa: embassy/appointment/staff-assignment fields + a
        // per-country/type configurable document checklist.
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->string('embassy')->nullable()->after('destination_country');
            $table->timestamp('appointment_date')->nullable()->after('submission_date');
            $table->date('expected_completion_date')->nullable()->after('appointment_date');
            $table->foreignId('assigned_to')->nullable()->after('agency_reference')->constrained('users')->nullOnDelete();
        });

        Schema::create('visa_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('destination_country', 2);
            $table->string('visa_type');
            $table->string('document_name');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'destination_country', 'visa_type']);
        });

        Schema::create('visa_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visa_document_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_name');
            $table->string('status')->default('missing'); // missing, submitted, verified, rejected
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_checklist_items');
        Schema::dropIfExists('visa_document_requirements');

        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['embassy', 'appointment_date', 'expected_completion_date']);
        });

        Schema::dropIfExists('hotel_booking_extra_services');

        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_room_type_id');
        });

        Schema::dropIfExists('hotel_extra_services');
        Schema::dropIfExists('hotel_room_types');

        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->dropColumn(['pnr', 'cabin_class', 'baggage_allowance', 'fare_type', 'refund_status', 'refund_amount', 'cancelled_at']);
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
