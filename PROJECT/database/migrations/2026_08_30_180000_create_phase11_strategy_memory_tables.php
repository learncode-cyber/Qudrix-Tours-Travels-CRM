<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable sales methodologies (Directive S8). An admin enables
        // strategies, sets their priority and tone, edits the prompt text,
        // and optionally binds a strategy to a customer segment.
        Schema::create('sales_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // consultative, spin, solution, value, relationship, challenger, sandler
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('prompt_guidance');
            $table->string('tone')->default('professional');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('customer_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'is_active', 'priority']);
        });

        // Structured customer memory (Directive S9). Deliberately typed and
        // categorised rather than a free-text blob, so it can be shown,
        // edited, and deleted per entry — the directive requires memory to
        // be permission-controlled, editable, deletable and auditable.
        Schema::create('customer_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category'); // budget, travel_preference, destination, group_size,
                                        // previous_trip, preferred_channel, objection, requirement
            $table->string('key');
            $table->text('value');
            $table->string('source')->default('human'); // human, ai_extracted
            $table->decimal('confidence', 3, 2)->nullable(); // only meaningful for ai_extracted
            // Marks an entry a human flagged as sensitive. Sensitive entries
            // are excluded from AI prompt context by default so the model is
            // not fed personal data it does not need (Directive S9).
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_memories');
        Schema::dropIfExists('sales_strategies');
    }
};
