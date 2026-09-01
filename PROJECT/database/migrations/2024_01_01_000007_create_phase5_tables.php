<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hajj_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('max_capacity');
            $table->json('rituals_included')->nullable();
            $table->json('accommodations')->nullable();
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('umrah_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('max_capacity');
            $table->json('rituals_included')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('destination');
            $table->integer('duration_days');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->integer('max_capacity');
            $table->json('activities')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date');
            $table->string('paid_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['airline', 'hotel', 'transport', 'visa', 'guide', 'other']);
            $table->string('email');
            $table->string('phone');
            $table->string('contact_person')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->text('contract_terms')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolution_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('ritual_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('ritual_name');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_date')->nullable();
            $table->text('notes')->nullable();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('ritual_checkpoints');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('tour_packages');
        Schema::dropIfExists('umrah_packages');
        Schema::dropIfExists('hajj_packages');
    }
};
