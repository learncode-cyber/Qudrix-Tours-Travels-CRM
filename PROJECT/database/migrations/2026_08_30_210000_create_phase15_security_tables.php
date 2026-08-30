<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Directive S19 requires access logs capturing IP, URL, method,
        // user agent, status code and timestamp. This is separate from
        // audit_logs: audit records WHAT BUSINESS DATA changed, this
        // records WHO TOUCHED THE SYSTEM AND HOW.
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->text('url');
            $table->string('route_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            // Flags a request worth a human look: auth failure, forbidden,
            // server error, or unusually slow.
            $table->boolean('is_suspicious')->default(false);
            $table->string('suspicion_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['is_suspicious', 'created_at']);
            $table->index('ip_address');
        });

        // Failed logins are recorded even when the email does not exist, so
        // enumeration attempts are visible.
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('reason')->nullable(); // unknown_email, bad_password, inactive_account, locked_out
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
        Schema::dropIfExists('access_logs');
    }
};
