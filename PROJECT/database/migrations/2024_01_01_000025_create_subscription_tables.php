<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->integer('max_users')->nullable();
            $table->integer('max_api_calls_per_day')->nullable();
            $table->integer('max_contacts')->nullable();
            $table->json('features')->nullable(); // ['webhooks', 'ai_agents', 'automation', ...]
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans')->onDelete('restrict');
            $table->enum('status', ['active', 'past_due', 'canceled', 'paused', 'pending'])->default('active');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->dateTime('current_period_start')->nullable();
            $table->dateTime('current_period_end')->nullable();
            $table->dateTime('renewal_date')->nullable();
            $table->dateTime('cancel_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
            $table->decimal('next_billing_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('restrict');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->enum('status', ['draft', 'sent', 'viewed', 'paid', 'overdue', 'canceled'])->default('draft');
            $table->dateTime('due_date');
            $table->dateTime('issued_date');
            $table->dateTime('paid_date')->nullable();
            $table->string('payment_gateway_id')->nullable(); // 'stripe', 'razorpay', etc.
            $table->string('payment_gateway_ref')->nullable();
            $table->string('pdf_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['subscription_id', 'status']);
        });

        Schema::create('subscription_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('metric_key'); // 'api_calls', 'contacts_created', etc.
            $table->integer('value')->default(0);
            $table->dateTime('reset_date')->nullable();
            $table->dateTime('logged_at')->useCurrent();
            $table->timestamps();
            $table->index(['tenant_id', 'subscription_id', 'metric_key']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'payment_method_id')) {
                // Column created above in create_subscriptions
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage_logs');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
