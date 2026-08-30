<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('description')->nullable();
            $table->json('default_items')->nullable();
            $table->json('default_payment_terms')->nullable();
            $table->unsignedInteger('default_validity_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('quotation_number');
            $table->boolean('requires_approval')->default(false)->after('status');
            $table->foreignId('approved_by')->nullable()->after('requires_approval')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('quotation_template_id')->nullable()->after('approved_at')->constrained('quotation_templates')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('quotation_template_id');
            $table->foreignId('supersedes_quotation_id')->nullable()->after('version')->constrained('quotations')->nullOnDelete();
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable()->after('unit_price');
            $table->decimal('markup_percentage', 5, 2)->nullable()->after('cost_price');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('draft'); // draft, sent, partially_paid, paid, overdue, cancelled
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('issue_date');
            $table->date('due_date');
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'markup_percentage']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_quotation_id');
            $table->dropColumn('version');
            $table->dropConstrainedForeignId('quotation_template_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('requires_approval');
            $table->dropColumn('share_token');
        });

        Schema::dropIfExists('quotation_templates');
    }
};
