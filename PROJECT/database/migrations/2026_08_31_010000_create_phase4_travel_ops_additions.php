<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 4 (Master Directive: Travel Operations) additions: an Embassy
// entity (the "embassy" column on visa_applications was a plain string
// with no address/contact/processing-time info anywhere), and hotel
// room blocks (holding inventory for a group before individual bookings
// are made against it — distinct from a regular hotel_booking).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embassies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('country');
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website')->nullable();
            $table->unsignedInteger('average_processing_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'country']);
        });

        Schema::table('visa_applications', function (Blueprint $table) {
            $table->foreignId('embassy_id')->nullable()->after('embassy')
                ->constrained('embassies')->nullOnDelete();
        });

        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable(); // e.g. "Ramadan Umrah Group A"
            $table->unsignedInteger('blocked_rooms');
            $table->unsignedInteger('released_rooms')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('held'); // held, partially_released, released, expired
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_blocks');
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('embassy_id');
        });
        Schema::dropIfExists('embassies');
    }
};
