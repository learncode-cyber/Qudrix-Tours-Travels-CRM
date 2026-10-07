<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 5: "Departures" (Hajj/Umrah spec) is the same concept as the
 * existing GroupBooking (a set of bookings travelling together) plus a
 * departure date and which package they're travelling on. Extending the
 * existing model rather than creating a parallel "Departure" entity that
 * would duplicate group-membership logic already built in Phase 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_bookings', function (Blueprint $table) {
            $table->timestamp('departure_date')->nullable()->after('total_members');
            $table->timestamp('return_date')->nullable()->after('departure_date');
            $table->string('package_type')->nullable()->after('return_date'); // hajj, umrah, tour
            $table->unsignedBigInteger('package_id')->nullable()->after('package_type');
        });
    }

    public function down(): void
    {
        Schema::table('group_bookings', function (Blueprint $table) {
            $table->dropColumn(['departure_date', 'return_date', 'package_type', 'package_id']);
        });
    }
};
