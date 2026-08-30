<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            // Optional override for self-hosted / proxied / compatible
            // endpoints. Null means the adapter's documented default host.
            $table->string('base_url')->nullable()->after('model');

            // Cost rates are OPERATOR-CONFIGURED, never hardcoded: published
            // prices change and differ per contract, so fabricating them in
            // code would make every cost figure in the system wrong and
            // unauditable. Null rates mean "cost unknown" and are reported
            // as such rather than as zero.
            $table->decimal('input_cost_per_million', 10, 4)->nullable()->after('monthly_cost_limit_usd');
            $table->decimal('output_cost_per_million', 10, 4)->nullable()->after('input_cost_per_million');

            $table->unsignedInteger('max_output_tokens')->default(4096)->after('output_cost_per_million');
            $table->timestamp('last_test_at')->nullable()->after('priority');
            $table->text('last_test_error')->nullable()->after('last_test_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn([
                'base_url', 'input_cost_per_million', 'output_cost_per_million',
                'max_output_tokens', 'last_test_at', 'last_test_error',
            ]);
        });
    }
};
