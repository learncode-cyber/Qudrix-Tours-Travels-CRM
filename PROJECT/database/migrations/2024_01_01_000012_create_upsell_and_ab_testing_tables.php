<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 13: Upsell/Cross-sell + A/B Testing. Genuinely new — nothing
 * existed here before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upsell_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category'); // visa, insurance, hotel_upgrade, tour_guide, transfer, premium_package, additional_service
            $table->string('applies_to_destination')->nullable(); // null = any destination
            $table->string('applies_to_booking_type')->nullable(); // null = any booking type
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject_type'); // sales_script, offer, pricing_presentation, package_presentation, cta, follow_up_message
            $table->string('status')->default('draft'); // draft, running, completed
            $table->foreignId('winning_variant_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Variant A", "Control"
            $table->text('content'); // the actual script/offer/CTA text being tested
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('engagements')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_variants');
        Schema::dropIfExists('experiments');
        Schema::dropIfExists('upsell_offers');
    }
};
