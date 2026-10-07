<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 18: Multi-tenant SaaS readiness. Tenant already had timezone/
 * currency/plan (string)/trial_ends_at from Phase 1 — adding what was
 * missing: language (multi-language requirement needs a tenant default),
 * branding fields, and a real SubscriptionPlan table so "plan" becomes
 * an actual entitlements record instead of a bare string with no
 * defined meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->integer('max_users')->nullable();
            $table->integer('max_bookings_per_month')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('language', 10)->default('en')->after('currency');
            $table->string('logo_url')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->foreignId('plan_id')->nullable()->after('plan')->constrained('subscription_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['language', 'logo_url', 'primary_color', 'plan_id']);
        });

        Schema::dropIfExists('subscription_plans');
    }
};
