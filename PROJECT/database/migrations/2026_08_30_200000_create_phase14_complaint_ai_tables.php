<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI triage output for a support ticket (Directive S15). Stored
        // separately from the ticket itself so the AI's read is always
        // distinguishable from what a human decided — the ticket's own
        // priority/status stay human-owned unless a human applies the
        // suggestion.
        Schema::create('ticket_ai_triages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('suggested_severity')->nullable();  // low, medium, high, critical
            $table->string('suggested_category')->nullable();
            $table->longText('suggested_response')->nullable();
            $table->longText('suggested_resolution')->nullable();
            $table->boolean('recommends_escalation')->default(false);
            $table->text('escalation_reason')->nullable();
            $table->string('sentiment')->nullable();
            $table->json('detected_issues')->nullable();
            // Set when a human accepts the triage and applies it to the
            // ticket, so "suggested" and "acted on" are never conflated.
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'support_ticket_id']);
        });

        // Auto-escalation happens on the ticket itself; record why, so an
        // automatic escalation is auditable after the fact.
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('escalation_source')->nullable()->after('escalated_to'); // human, ai_critical
            $table->text('escalation_note')->nullable()->after('escalation_source');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['escalation_source', 'escalation_note']);
        });

        Schema::dropIfExists('ticket_ai_triages');
    }
};
