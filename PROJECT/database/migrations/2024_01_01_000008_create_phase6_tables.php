<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['booking_created', 'customer_added', 'invoice_created', 'payment_received', 'webhook', 'schedule']);
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->boolean('is_active')->default(false);
            $table->integer('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'trigger_type']);
            $table->index(['is_active', 'status']);
        });
        
        Schema::create('automation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->integer('step_order');
            $table->enum('action_type', ['send_email', 'send_sms', 'create_task', 'update_customer', 'create_notification', 'webhook', 'delay']);
            $table->json('action_config');
            $table->string('condition_type')->nullable();
            $table->json('condition_config')->nullable();
            $table->integer('delay_seconds')->default(0);
            $table->index(['automation_id', 'step_order']);
        });
        
        Schema::create('automation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->json('workflow_config');
            $table->json('preview_data')->nullable();
            $table->integer('usage_count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'category']);
        });
        
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->json('trigger_data')->nullable();
            $table->enum('status', ['running', 'success', 'error', 'skipped']);
            $table->json('result_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->index(['automation_id', 'status']);
            $table->index(['started_at']);
        });
        
        Schema::create('automation_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->json('widgets');
            $table->integer('refresh_interval')->default(30);
            $table->index(['tenant_id', 'user_id']);
        });
        
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->index(['event_type', 'status']);
            $table->index(['automation_id']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('automation_dashboards');
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_templates');
        Schema::dropIfExists('automation_steps');
        Schema::dropIfExists('automations');
    }
};
