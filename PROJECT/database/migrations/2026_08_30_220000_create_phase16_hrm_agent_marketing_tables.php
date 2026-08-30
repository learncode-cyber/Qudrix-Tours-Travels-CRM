<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- HRM (Directive S3.M) ----------
        // Employee already exists from Phase 0; these complete the module.
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status')->default('present'); // present, absent, late, half_day, leave, holiday
            $table->text('note')->nullable();
            $table->timestamps();

            // One attendance row per employee per day.
            $table->unique(['employee_id', 'work_date']);
            $table->index(['tenant_id', 'work_date']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('annual_quota_days')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'holiday_date', 'name']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // YYYY-MM
            $table->string('status')->default('draft'); // draft, approved, paid
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->unsignedInteger('days_present')->nullable();
            $table->unsignedInteger('days_absent')->nullable();
            $table->text('note')->nullable();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('basic_salary', 12, 2)->nullable()->after('employment_status');
            $table->string('salary_currency', 3)->default('USD')->after('basic_salary');
        });

        // ---------- B2B Agent Management (Directive S3.L) ----------
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Optional portal login for the agent.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agency_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('agent_code')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);
            // Money the agency owes the agent (earned, unpaid commission).
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status')->default('pending'); // pending, approved, suspended, rejected
            $table->string('kyc_status')->default('not_submitted'); // not_submitted, submitted, verified, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('booking_amount', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, approved, paid, cancelled
            $table->foreignId('agent_payout_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'agent_id', 'status']);
        });

        Schema::create('agent_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'agent_id', 'status']);
        });

        // Bookings can originate from an agent.
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('assigned_to')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });

        Schema::dropIfExists('agent_payouts');
        Schema::dropIfExists('agent_commissions');
        Schema::dropIfExists('agents');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['basic_salary', 'salary_currency']);
        });

        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendances');
    }
};
