<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 10: AI Sales Agent conversations. Genuinely new — nothing
 * existed for message history/conversation state anywhere in the
 * project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('channel')->default('web_chat'); // web_chat, whatsapp, etc.
            $table->string('status')->default('active'); // active, needs_human, escalated, closed
            $table->string('buying_intent')->nullable(); // low, medium, high — set by the agent, not invented at query time
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // staff handling handoff
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // customer, agent, system, staff
            $table->text('content');
            $table->json('metadata')->nullable(); // e.g. tokens used, detected requirements
            $table->timestamps();

            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
