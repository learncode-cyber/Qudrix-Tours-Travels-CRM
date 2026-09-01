<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WEBSITE INTEGRATIONS TABLE
        // Stores configuration for each website connecting to CRM
        Schema::create('website_integrations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_id')->unsigned();
            $table->string('name')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->text('description')->nullable();
            
            // Encrypted CRM credentials
            $table->text('crm_api_key')->nullable();
            $table->text('crm_api_secret')->nullable();
            $table->string('crm_base_url', 500)->default('https://yourdomain.com/api/v1');
            
            // Webhook configuration
            $table->text('webhook_secret')->nullable();
            $table->string('webhook_url', 500)->nullable();
            
            // Sync configuration
            $table->json('sync_settings')->nullable();
            $table->json('custom_mappings')->nullable();
            
            // Status tracking
            $table->enum('status', ['pending', 'connected', 'error', 'disconnected'])->default('pending');
            $table->boolean('is_active')->default(true);
            $table->enum('integration_type', ['website', 'external_system', 'mobile_app'])->default('website');
            
            // Health tracking
            $table->timestamp('last_connection_test_at')->nullable();
            $table->string('last_connection_status', 50)->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_sync_error')->nullable();
            
            // Metadata
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('is_active');
        });

        // INTEGRATION SYNC LOGS TABLE
        // Tracks all data sync operations
        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('website_integration_id')->unsigned();
            $table->string('sync_type');  // manual, scheduled, webhook, import
            $table->string('entity_type'); // leads, customers, bookings, quotations
            $table->integer('entity_count')->default(0);
            $table->enum('status', ['pending', 'success', 'failed', 'partial'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->json('data_sent')->nullable();
            $table->json('data_received')->nullable();
            $table->timestamps();
            
            $table->foreign('website_integration_id')
                ->references('id')->on('website_integrations')
                ->onDelete('cascade');
            $table->index('website_integration_id');
            $table->index('status');
            $table->index('entity_type');
            $table->index('created_at');
        });

        // INTEGRATION AUDIT LOGS TABLE
        // Tracks changes to integration configuration
        Schema::create('integration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('website_integration_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('action'); // create, update, delete, connect_test, credentials_change
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
            
            $table->foreign('website_integration_id')
                ->references('id')->on('website_integrations')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->index('website_integration_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_audit_logs');
        Schema::dropIfExists('integration_sync_logs');
        Schema::dropIfExists('website_integrations');
    }
};
