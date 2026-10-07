<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRITICAL FINDING (2026-09-25, reviewing the aggregate webhook monitoring
 * endpoints flagged as a follow-up in CRITICAL_SECURITY_WEBHOOK_IDOR_REPORT.md):
 * `webhook_audit_logs`, `webhook_delivery_audit_logs`, and
 * `webhook_security_audit_logs` are read from and written to throughout
 * WebhookAuditLoggingService — but no migration anywhere in the project
 * ever created them. Every audit-trail/compliance/security-log endpoint
 * (getAuditTrail, getDeliveryAuditTrail, getSecurityAuditLog,
 * generateComplianceReport, exportAuditLog) has been throwing a SQL
 * "table not found" error on every call since whichever phase introduced
 * this service — a complete functional failure, not just a security gap.
 *
 * Since fixing the crash means creating these tables for the first time,
 * `tenant_id` is added directly on all three from the start, closing the
 * cross-tenant information-disclosure risk this would otherwise have
 * reintroduced the moment the tables existed (webhook_audit_logs and
 * webhook_delivery_audit_logs are also joinable via webhook_id, but a
 * direct column avoids a join on every query and matches this project's
 * established convention). webhook_security_audit_logs.tenant_id is
 * nullable because some security events (e.g. an auth failure with an
 * invalid/unrecognized API key) may not be attributable to a tenant at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('changes')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'webhook_id']);
        });

        Schema::create('webhook_delivery_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->text('request_payload')->nullable();
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('delivery_time_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->integer('retry_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'webhook_id']);
        });

        Schema::create('webhook_security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('severity');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_security_audit_logs');
        Schema::dropIfExists('webhook_delivery_audit_logs');
        Schema::dropIfExists('webhook_audit_logs');
    }
};
