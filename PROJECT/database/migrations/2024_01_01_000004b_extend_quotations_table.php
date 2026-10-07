<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 3 AUDIT FINDING: App\Http\Controllers\Api\PublicQuotationController
 * wrote to fields (package_id, travel_date, number_of_travelers,
 * special_requirements, quoted_budget) that don't exist in
 * $fillable/migration for `quotations` — Eloquent silently drops
 * non-fillable mass-assigned fields, so every public quotation request
 * quietly lost that data. Adding real columns rather than continuing to
 * discard it. Also making `created_by` nullable: it was NOT NULL/
 * restricted to `users`, but public/website-sourced quotations have no
 * acting staff user at creation time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('customer_id')->constrained('packages')->nullOnDelete();
            $table->timestamp('travel_date')->nullable()->after('valid_until');
            $table->integer('number_of_travelers')->nullable()->after('travel_date');
            $table->text('special_requirements')->nullable()->after('notes');
            $table->decimal('quoted_budget', 12, 2)->nullable()->after('special_requirements');
            $table->integer('version')->default(1);
            $table->foreignId('parent_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('approval_status')->default('not_required'); // not_required, pending, approved, rejected
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['parent_quotation_id']);
            $table->dropColumn([
                'package_id', 'travel_date', 'number_of_travelers',
                'special_requirements', 'quoted_budget', 'version',
                'parent_quotation_id', 'approval_status',
            ]);
        });
    }
};
