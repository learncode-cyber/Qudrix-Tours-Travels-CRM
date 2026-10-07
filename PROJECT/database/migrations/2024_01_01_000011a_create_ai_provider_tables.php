<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 9: AI Provider Management — completely greenfield. The Phase 0
 * handover docs claimed "AI Provider Manager skeleton, Encrypted AI
 * credentials" already existed; audit found zero AI-related code
 * anywhere in the project, matching the pattern of every prior phase's
 * doc-vs-reality gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // gemini, openai, anthropic
            $table->string('name'); // tenant's own label, e.g. "Production Gemini"
            $table->text('api_key_encrypted'); // Laravel's native encrypted cast, not a custom scheme
            $table->string('default_model')->nullable();
            $table->integer('max_tokens')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            // Tenant-supplied, not hardcoded here — provider pricing
            // changes over time and varies by plan/region, so this app
            // never assumes a number; cost estimates in ai_usage_logs are
            // simply null until a tenant configures these.
            $table->decimal('cost_per_1k_prompt_tokens', 10, 6)->nullable();
            $table->decimal('cost_per_1k_completion_tokens', 10, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('fallback_priority')->nullable(); // lower = tried first if default fails
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed, not_tested
            $table->text('last_test_detail')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('ai_feature_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key'); // e.g. 'sales_agent', 'package_builder', 'lead_scoring'
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('model_override')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('feature_key')->nullable();
            $table->string('model')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->string('status'); // success, failed, blocked
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_feature_configs');
        Schema::dropIfExists('ai_providers');
    }
};
