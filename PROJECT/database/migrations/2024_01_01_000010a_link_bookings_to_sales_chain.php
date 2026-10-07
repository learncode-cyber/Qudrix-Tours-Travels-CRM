<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 8 AUDIT FINDING: Invoice (Phase 3) links back to proposal_id and
 * quotation_id, but Booking never did — meaning a booking created from a
 * signed deal had no traceable link back to the quotation/proposal that
 * closed it. This breaks the "Lead -> Deal -> Quotation -> Booking ->
 * Invoice" chain the phase spec explicitly requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('package_id')->constrained('quotations')->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->after('quotation_id')->constrained('proposals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['proposal_id']);
            $table->dropColumn(['quotation_id', 'proposal_id']);
        });
    }
};
