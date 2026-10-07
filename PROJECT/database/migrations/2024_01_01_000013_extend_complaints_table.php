<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 14: complaints (Phase 0) had no SLA tracking, no escalation
 * state, and no gate on refund/compensation resolutions — the phase
 * spec explicitly requires "AI must not promise refunds/compensation
 * unless company rules explicitly allow it."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->timestamp('sla_deadline')->nullable()->after('priority');
            $table->boolean('sla_breached')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->boolean('involves_compensation')->default(false);
            $table->string('approval_status')->default('not_required'); // not_required, pending, approved, rejected
            $table->decimal('compensation_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn([
                'sla_deadline', 'sla_breached', 'escalated_at',
                'involves_compensation', 'approval_status', 'compensation_amount',
            ]);
        });
    }
};
