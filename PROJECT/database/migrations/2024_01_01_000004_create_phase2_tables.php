<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quotations table
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('quotation_number')->unique();
            $table->string('subject');
            $table->longText('description')->nullable();
            $table->string('status')->default('draft'); // draft, sent, accepted, rejected
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->timestamp('valid_until')->nullable();
            $table->json('payment_terms')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('status');
        });

        // Quotation Items table
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->onDelete('cascade');
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->index('quotation_id');
        });

        // Proposals table
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('quotation_id')->constrained('quotations')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('proposal_number')->unique();
            $table->string('status')->default('draft'); // draft, sent, signed, rejected
            $table->string('title');
            $table->longText('description')->nullable();
            $table->timestamp('proposal_date')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->timestamp('sent_date')->nullable();
            $table->timestamp('signed_date')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('lead_id');
        });

        // Deal Stages table
        Schema::create('deal_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->string('stage'); // new, contacted, qualified, proposal, negotiation, won, lost
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->integer('duration_days')->nullable();
            $table->string('notes')->nullable();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('stage');
        });

        // Sales Activities table
        Schema::create('sales_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('activity_type'); // call, email, meeting, note, proposal_sent, quote_sent
            $table->string('title');
            $table->longText('description')->nullable();
            $table->timestamp('activity_date')->nullable();
            $table->string('outcome')->nullable(); // positive, neutral, negative

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index('activity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_activities');
        Schema::dropIfExists('deal_stages');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
