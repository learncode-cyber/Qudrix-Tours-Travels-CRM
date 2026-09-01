<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('metric_type');
            $table->decimal('metric_value', 15, 2);
            $table->enum('period', ['hourly', 'daily', 'weekly', 'monthly']);
            $table->timestamp('recorded_date')->nullable();
            $table->index(['tenant_id', 'metric_type']);
            $table->index(['recorded_date']);
        });
        
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('report_type', ['booking', 'revenue', 'customer', 'travel', 'performance']);
            $table->json('filters')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['draft', 'generating', 'completed', 'failed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'report_type']);
        });
        
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->json('recipients');
            $table->timestamp('next_run_at');
            $table->boolean('is_active')->default(true);
            $table->index(['report_id', 'is_active']);
        });
        
        Schema::create('data_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type');
            $table->string('title');
            $table->text('description');
            $table->json('data')->nullable();
            $table->enum('impact_level', ['low', 'medium', 'high']);
            $table->text('recommended_action')->nullable();
            $table->timestamp('generated_at');
            $table->index(['tenant_id', 'insight_type']);
            $table->index(['impact_level']);
        });
        
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria');
            $table->integer('member_count')->default(0);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
        });
        
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('prediction_type');
            $table->decimal('predicted_value', 12, 2);
            $table->decimal('confidence_score', 5, 2);
            $table->text('reasoning')->nullable();
            $table->timestamp('predicted_at');
            $table->index(['tenant_id', 'entity_type', 'prediction_type']);
        });
        
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->json('widgets')->nullable();
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'user_id']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('dashboards');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('data_insights');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('analytics');
    }
};
