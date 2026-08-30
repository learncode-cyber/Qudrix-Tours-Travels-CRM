<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rule-based, deterministic pricing engine (Directive S7): each rule
        // is an explicit, admin-configured adjustment — never an LLM
        // decision — so every price is reproducible and auditable.
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('factor'); // season, demand, group_size, customer_segment, booking_timing
            $table->date('season_start')->nullable();
            $table->date('season_end')->nullable();
            $table->unsignedInteger('min_group_size')->nullable();
            $table->unsignedInteger('max_group_size')->nullable();
            $table->unsignedInteger('booking_days_before_travel_min')->nullable();
            $table->unsignedInteger('booking_days_before_travel_max')->nullable();
            $table->foreignId('customer_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('adjustment_type'); // percentage, fixed
            $table->decimal('adjustment_value', 10, 2);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'factor', 'is_active']);
        });

        // One row per computed price — the audit trail the directive
        // requires ("final price calculation must remain deterministic and
        // auditable").
        Schema::create('pricing_calculation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_cost', 12, 2);
            $table->json('context'); // group_size, travel_date, customer_segment_id, currency, ...
            $table->json('applied_rules'); // [{rule_id, name, adjustment_type, adjustment_value, amount}]
            $table->decimal('final_price', 12, 2);
            $table->timestamp('created_at')->useCurrent();
        });

        // Extends the existing generic Package model to support packages
        // assembled by the Custom Package Builder from real inventory
        // (as opposed to a manually authored package).
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_custom_built')->default(false)->after('status');
            $table->json('components')->nullable()->after('is_custom_built');
            $table->foreignId('built_by')->nullable()->after('components')->constrained('users')->nullOnDelete();
            $table->foreignId('built_for_customer_id')->nullable()->after('built_by')->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('built_for_customer_id');
            $table->dropConstrainedForeignId('built_by');
            $table->dropColumn(['is_custom_built', 'components']);
        });

        Schema::dropIfExists('pricing_calculation_logs');
        Schema::dropIfExists('pricing_rules');
    }
};
