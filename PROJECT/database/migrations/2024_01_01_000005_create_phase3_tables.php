<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bookings table
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('package_id')->constrained('packages')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('group_booking_id')->nullable()->constrained('group_bookings')->onDelete('set null');
            $table->string('booking_number')->unique();
            $table->string('booking_type'); // individual, group, corporate
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed
            $table->timestamp('travel_date');
            $table->timestamp('return_date');
            $table->integer('number_of_travelers');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_status')->default('pending'); // pending, paid, partial
            $table->timestamp('confirmation_date')->nullable();
            $table->json('special_requests')->nullable();
            $table->boolean('visa_required')->default(false);
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('travel_date');
        });

        // Booking Travelers table
        Schema::create('booking_travelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->date('date_of_birth');
            $table->string('gender'); // male, female, other
            $table->string('passport_number');
            $table->date('passport_expiry');
            $table->string('national_id')->nullable();
            $table->string('nationality'); // ISO 2-letter code
            $table->string('traveler_type'); // adult, child, infant
            $table->boolean('is_primary_contact')->default(false);
            $table->string('emergency_contact');
            $table->string('emergency_phone');
            $table->string('room_preference')->nullable();

            $table->index('booking_id');
        });

        // Booking Itinerary table
        Schema::create('booking_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->integer('day_number');
            $table->date('date');
            $table->string('location');
            $table->string('activity_type'); // sightseeing, hotel, flight, transport, meal, worship
            $table->string('activity_name');
            $table->longText('description')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('meal_type')->nullable(); // breakfast, lunch, dinner, all
            $table->string('transportation_type')->nullable(); // bus, flight, train, car
            $table->longText('notes')->nullable();

            $table->index('booking_id');
            $table->index('day_number');
        });

        // Group Bookings table
        Schema::create('group_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('group_leader_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('group_name');
            $table->integer('total_members');
            $table->string('status')->default('active'); // active, inactive, completed
            $table->longText('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('group_leader_id');
        });

        // Booking Confirmations table
        Schema::create('booking_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('confirmed_by')->constrained('users')->onDelete('restrict');
            $table->string('confirmation_number')->unique();
            $table->timestamp('confirmation_date');
            $table->string('confirmation_method'); // system, email, phone, manual
            $table->string('reference_code')->nullable();
            $table->string('provider_confirmation_id')->nullable();
            $table->longText('notes')->nullable();

            $table->index('tenant_id');
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_confirmations');
        Schema::dropIfExists('group_bookings');
        Schema::dropIfExists('booking_itineraries');
        Schema::dropIfExists('booking_travelers');
        Schema::dropIfExists('bookings');
    }
};
