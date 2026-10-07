<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 6: pricing rules engine. Deliberately data-driven (rules stored
 * per-tenant, evaluated at request time) rather than hardcoded
 * percentages in PHP — a business's season/demand/group-size factors are
 * their own commercial decisions, not something to bake into code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('rule_type', ['season', 'demand', 'group_size', 'booking_timing', 'customer_segment']);
            // conditions: rule-type-specific matching criteria, e.g.
            //   season:          {"start_month": 6, "end_month": 8}
            //   group_size:      {"min_travelers": 10}
            //   booking_timing:  {"days_before_travel_min": 60}  (early bird)
            //                    {"days_before_travel_max": 3}   (last minute)
            //   customer_segment:{"segment_id": 4}
            //   demand:          {"destination": "Egypt"}  (paired with manual demand_level below)
            $table->json('conditions');
            $table->enum('adjustment_type', ['percentage', 'fixed']);
            $table->decimal('adjustment_value', 8, 2); // positive = surcharge, negative = discount
            $table->integer('priority')->default(0); // lower runs first
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'rule_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
