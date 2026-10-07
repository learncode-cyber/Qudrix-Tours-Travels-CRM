<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 3: payments previously only linked to bookings, but invoices
 * (new this phase) need to accept payments too. Also adds basic
 * commission tracking (rate configured on the Proposal at sign-time,
 * amount computed when a payment against the resulting invoice completes)
 * — a real, if simple, implementation rather than a stub field nobody
 * populates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('booking_id')->constrained('invoices')->nullOnDelete();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['invoice_id', 'commission_rate', 'commission_amount']);
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
