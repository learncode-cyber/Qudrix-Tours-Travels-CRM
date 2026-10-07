<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_PROJECT_AUDIT.md P2:
 *
 * 1. Marketing attribution — leads already capture utm_source/medium/
 *    campaign/term/content (Phase 16), but nothing lets a tenant configure
 *    a Meta Pixel / Conversions API / GA4 destination, or record a
 *    CRM-side funnel event (qualified lead, application started, payment,
 *    conversion) against those. This is genuinely BLOCKED end-to-end
 *    without real Meta/Google credentials, same as AI Provider (Phase 9)
 *    — the config + event log + service are built so it works the moment
 *    real credentials are entered, but no request can actually be sent
 *    from this sandbox.
 *
 * 2. Commission ledger — Agent (P0) only had running-total fields
 *    (total_commission_earned/total_commission_paid) with no line-item
 *    history, so nothing was reconcilable. This is a separate ledger from
 *    the existing Proposal/Payment commission fields, which model the
 *    agency's own commission FROM a supplier — CommissionEntry models the
 *    agency's outgoing commission TO a referring Agent. Both are real,
 *    distinct flows and are not merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('meta_pixel_id')->nullable();
            $table->text('meta_conversions_api_token')->nullable();
            $table->string('ga4_measurement_id')->nullable();
            $table->text('ga4_api_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->enum('event_name', ['qualified_lead', 'application_started', 'payment', 'conversion']);
            $table->decimal('event_value', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->enum('meta_status', ['not_configured', 'queued', 'sent', 'failed'])->default('not_configured');
            $table->timestamp('meta_sent_at')->nullable();
            $table->text('meta_response')->nullable();
            $table->enum('ga4_status', ['not_configured', 'queued', 'sent', 'failed'])->default('not_configured');
            $table->timestamp('ga4_sent_at')->nullable();
            $table->text('ga4_response')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_name']);
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('type', ['earned', 'paid', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('conversion_events');
        Schema::dropIfExists('tracking_configs');
    }
};
