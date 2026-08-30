<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bookings and quotations created through the PUBLIC website API have no
// CRM user behind them — `created_by` was a non-nullable FK to `users`,
// so the public endpoints could never have inserted a row (they passed the
// string 'website_api' into an integer FK column).
//
// Making it nullable is the honest modelling: a website-originated record
// genuinely has no internal creator. `source` records where it came from,
// so the two cases stay distinguishable in reporting.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
            $table->string('source')->default('crm')->after('booking_type'); // crm, website, agent
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
            $table->foreignId('lead_id')->nullable()->change();
            $table->string('source')->default('crm')->after('status');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('source')->default('crm')->after('customer_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        // created_by / lead_id are intentionally left nullable on rollback:
        // reverting them would fail against any website-originated rows.
    }
};
