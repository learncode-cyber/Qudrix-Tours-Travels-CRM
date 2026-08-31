<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 2 (CRM): a genuine Deal entity distinct from Lead/DealStage.
// DealStage (see 2024_01_01_000004_create_phase2_tables.php) tracks a
// Lead's stage-transition history, not a standalone sales opportunity —
// Deal is the missing piece: a trackable opportunity with amount,
// probability, and an owner, optionally linked back to the lead/customer
// it came from.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('stage')->default('new'); // new, qualified, proposal, negotiation, won, lost
            $table->unsignedTinyInteger('probability')->default(0); // 0-100
            $table->date('expected_close_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'owner_id']);
        });

        Schema::create('deal_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->unsignedInteger('duration_days')->nullable();

            $table->index(['deal_id', 'entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_stage_transitions');
        Schema::dropIfExists('deals');
    }
};
