<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 11: Sales Strategies + AI Copilot support tables. Nothing here
 * existed before — genuinely new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // opening, discovery, closing, follow_up
            $table->string('title');
            $table->text('content');
            $table->string('strategy')->nullable(); // consultative, spin, solution, value, relationship, challenger, sandler
            $table->timestamps();
        });

        Schema::create('objection_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('objection'); // e.g. "too expensive"
            $table->text('suggested_response');
            $table->timestamps();
        });

        Schema::create('sales_strategy_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('strategy')->default('consultative');
            // consultative, spin, solution, value, relationship, challenger, sandler
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_strategy_configs');
        Schema::dropIfExists('objection_responses');
        Schema::dropIfExists('sales_scripts');
    }
};
