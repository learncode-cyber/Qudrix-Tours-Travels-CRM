<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offline_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('operation', ['create', 'update', 'delete']);
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'user_id']);
            $table->index(['status']);
        });
        
        Schema::create('sync_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('batch_id');
            $table->json('data');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('processed_at')->nullable();
            $table->index(['tenant_id', 'status']);
        });
        
        Schema::create('cache_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type');
            $table->enum('cache_strategy', ['network_first', 'cache_first', 'stale_while_revalidate']);
            $table->integer('ttl_minutes')->default(60);
            $table->integer('max_size_mb')->default(50);
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'is_active']);
        });
        
        Schema::create('pwa_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('app_name')->default('QUDRIX Travel CRM');
            $table->string('app_short_name')->default('QUDRIX');
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('theme_color')->default('#1976d2');
            $table->string('background_color')->default('#ffffff');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('offline_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);
            $table->json('manifest_config')->nullable();
            $table->unique('tenant_id');
        });
        
        Schema::create('service_worker_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('cache_name');
            $table->text('resource_url');
            $table->enum('status', ['active', 'expired', 'pending'])->default('active');
            $table->timestamp('cached_at');
            $table->timestamp('expires_at');
            $table->index(['tenant_id', 'cache_name']);
            $table->index(['expires_at']);
        });
        
        Schema::create('offline_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('data_type');
            $table->json('data');
            $table->integer('size_kb')->default(0);
            $table->timestamp('last_synced')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'error'])->default('pending');
            $table->index(['tenant_id', 'user_id']);
            $table->index(['data_type']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('offline_data');
        Schema::dropIfExists('service_worker_caches');
        Schema::dropIfExists('pwa_settings');
        Schema::dropIfExists('cache_policies');
        Schema::dropIfExists('sync_queues');
        Schema::dropIfExists('offline_syncs');
    }
};
