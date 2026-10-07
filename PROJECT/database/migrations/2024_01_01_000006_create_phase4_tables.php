<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->string('airline_code');
            $table->string('flight_number')->unique();
            $table->string('departure_airport', 3);
            $table->string('arrival_airport', 3);
            $table->timestamp('departure_date');
            $table->timestamp('arrival_date');
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->string('aircraft_type');
            $table->integer('total_seats');
            $table->integer('available_seats');
            $table->decimal('price_per_seat', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('active');
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('departure_airport');
            $table->index('arrival_airport');
        });

        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('flight_id')->constrained('flights')->onDelete('cascade');
            $table->foreignId('booking_traveler_id')->constrained('booking_travelers')->onDelete('cascade');
            $table->string('seat_number');
            $table->string('ticket_number')->unique()->nullable();
            $table->string('status')->default('booked');
            $table->decimal('price_paid', 10, 2)->nullable();

            $table->index('booking_id');
            $table->index('flight_id');
        });

        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('city');
            $table->string('country');
            $table->longText('address');
            $table->string('phone');
            $table->string('email');
            $table->string('website')->nullable();
            $table->integer('star_rating');
            $table->longText('description')->nullable();
            $table->integer('total_rooms');
            $table->integer('available_rooms');
            $table->decimal('price_per_night', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->time('check_in_time')->default('14:00:00');
            $table->time('check_out_time')->default('11:00:00');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('city');
        });

        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('number_of_rooms');
            $table->integer('number_of_nights');
            $table->string('room_type');
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default('confirmed');
            $table->string('confirmation_number')->unique()->nullable();

            $table->index('booking_id');
            $table->index('hotel_id');
        });

        Schema::create('transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->string('transport_type');
            $table->string('vehicle_name');
            $table->string('vehicle_number');
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->date('pickup_date');
            $table->time('pickup_time');
            $table->time('dropoff_time')->nullable();
            $table->integer('capacity');
            $table->decimal('price_per_seat', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('driver_name');
            $table->string('driver_phone');
            $table->string('status')->default('active');
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        Schema::create('transport_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('transport_id')->constrained('transports')->onDelete('cascade');
            $table->foreignId('booking_traveler_id')->nullable()->constrained('booking_travelers')->onDelete('set null');
            $table->string('seat_number')->nullable();
            $table->string('status')->default('booked');

            $table->index('booking_id');
            $table->index('transport_id');
        });

        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('country');
            $table->string('city');
            $table->string('region')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('tourist_season')->nullable();
            $table->longText('weather_info')->nullable();
            $table->boolean('visa_required')->default(false);
            $table->string('currency', 3)->default('USD');
            $table->string('language')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('country');
        });

        Schema::create('visa_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('booking_traveler_id')->constrained('booking_travelers')->onDelete('cascade');
            $table->string('destination_country', 2);
            $table->string('visa_type');
            $table->date('application_date');
            $table->date('submission_date')->nullable();
            $table->date('approval_date')->nullable();
            $table->string('visa_number')->unique()->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('pending');
            $table->json('documents')->nullable();
            $table->longText('notes')->nullable();
            $table->string('agency_name')->nullable();
            $table->string('agency_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('booking_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_applications');
        Schema::dropIfExists('destinations');
        Schema::dropIfExists('transport_bookings');
        Schema::dropIfExists('transports');
        Schema::dropIfExists('hotel_bookings');
        Schema::dropIfExists('hotels');
        Schema::dropIfExists('flight_bookings');
        Schema::dropIfExists('flights');
    }
};
