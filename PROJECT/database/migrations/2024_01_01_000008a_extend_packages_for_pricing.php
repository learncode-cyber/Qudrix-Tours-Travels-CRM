<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 6: the pricing engine needs supplier cost and markup to compute
 * margin at all — Package only ever had base_price (the sell price),
 * with no record of what it costs the agency, so margin was
 * uncomputable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('supplier_cost', 12, 2)->nullable()->after('base_price');
            $table->decimal('markup_percentage', 5, 2)->nullable()->after('supplier_cost');
            $table->string('currency', 3)->default('USD')->after('markup_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['supplier_cost', 'markup_percentage', 'currency']);
        });
    }
};
