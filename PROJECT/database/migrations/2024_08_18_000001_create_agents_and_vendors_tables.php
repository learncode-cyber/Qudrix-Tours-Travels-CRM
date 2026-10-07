<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_PROJECT_AUDIT.md Part 3-4 / P0 finding:
 * `suppliers` only models operational travel-service providers
 * (airline/hotel/transport/visa/guide). The business also needs two
 * distinct partner relationships that were never separated out:
 *
 * - AGENT: an external person/sub-agent who refers or sells business on
 *   commission (B2B referral / freelance sales channel). Tracked against
 *   leads/bookings via `agent_id` so referral commission is attributable.
 * - VENDOR: a non-travel operational vendor to the business itself
 *   (printing, marketing, software, office supplies, etc.) — distinct
 *   from a travel-service `supplier`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('agent_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('company_name')->nullable();
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('commission_rate', 8, 2)->default(0);
            $table->decimal('total_commission_earned', 12, 2)->default(0);
            $table->decimal('total_commission_paid', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('category', ['printing', 'marketing', 'software', 'office_supplies', 'utilities', 'other'])->default('other');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('payment_terms')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        // Attribute leads/bookings to a referring agent without disturbing
        // any existing foreign keys — both columns are nullable additions.
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('tenant_id')->constrained('agents')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('tenant_id')->constrained('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('agents');
    }
};
