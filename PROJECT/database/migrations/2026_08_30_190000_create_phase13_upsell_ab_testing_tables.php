<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-configured cross-sell rules (Directive S11). Deliberately
        // rule-based: recommendations must be "based on configured
        // products/rules and actual availability", not model guesswork.
        Schema::create('upsell_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // When the customer books/has this...
            $table->string('trigger_type'); // flight, hotel, tour, visa, hajj, umrah, transport, any
            // ...recommend this.
            $table->string('recommend_type'); // hotel, visa, insurance, transport, tour_guide, transport, addon
            $table->text('description')->nullable();
            $table->decimal('suggested_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('requires_availability_check')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'trigger_type', 'is_active']);
        });

        // Records every recommendation shown and whether it converted, so
        // the engine's effectiveness is measured from real outcomes rather
        // than assumed.
        Schema::create('upsell_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upsell_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shown_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recommend_type');
            $table->string('outcome')->default('shown'); // shown, accepted, declined
            $table->decimal('accepted_value', 12, 2)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'outcome']);
        });

        // Sales script A/B testing (Directive S14).
        Schema::create('ab_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('hypothesis')->nullable();
            $table->string('subject_type')->default('sales_script'); // sales_script, email_template, follow_up_sequence
            $table->string('status')->default('draft'); // draft, running, stopped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ab_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_experiment_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // "A", "B", ...
            $table->longText('content');
            // Relative weight for assignment; equal weights = even split.
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ab_experiment_id', 'label']);
        });

        // One row per assignment. Outcomes are recorded against the
        // assignment, so every reported rate is computed from real events.
        Schema::create('ab_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ab_experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ab_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('responded')->default(false);
            $table->boolean('converted')->default(false);
            $table->decimal('booking_value', 12, 2)->nullable();
            $table->unsignedInteger('time_to_close_hours')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            // A lead is assigned to a given experiment exactly once, so it
            // cannot be counted twice or flip between variants.
            $table->unique(['ab_experiment_id', 'lead_id']);
            $table->index(['tenant_id', 'ab_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_assignments');
        Schema::dropIfExists('ab_variants');
        Schema::dropIfExists('ab_experiments');
        Schema::dropIfExists('upsell_recommendations');
        Schema::dropIfExists('upsell_rules');
    }
};
