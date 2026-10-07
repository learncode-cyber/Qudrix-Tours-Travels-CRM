<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API KEYS TABLE
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_id')->unsigned();
            $table->string('name')->default('Default API Key');
            $table->string('key', 100)->unique();
            $table->string('secret', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->integer('rate_limit')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->bigInteger('usage_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('key');
            $table->index('tenant_id');
        });

        // API LOGS TABLE
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_id')->unsigned();
            $table->bigInteger('api_key_id')->unsigned()->nullable();
            $table->string('method', 10);
            $table->string('endpoint', 255);
            $table->string('status_code', 3);
            $table->integer('response_time')->nullable();
            $table->json('request_data')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('set null');
            $table->index('tenant_id');
            $table->index('api_key_id');
            $table->index('endpoint');
            $table->index('created_at');
        });

        // WEBHOOKS TABLE
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_id')->unsigned();
            $table->string('url', 500);
            $table->string('event', 100);
            $table->json('events')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('retry_count')->default(3);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('event');
        });

        // WEBHOOK LOGS TABLE
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('webhook_id')->unsigned();
            $table->string('event', 100);
            $table->json('payload')->nullable();
            $table->string('status', 20);
            $table->string('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('triggered_at');
            $table->timestamps();
            
            $table->foreign('webhook_id')->references('id')->on('webhooks')->onDelete('cascade');
            $table->index('webhook_id');
            $table->index('status');
        });

        // API SETTINGS TABLE
        Schema::create('api_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_id')->unsigned();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_settings');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_keys');
    }
};
