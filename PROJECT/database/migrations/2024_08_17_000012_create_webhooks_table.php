<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NOTE: `webhooks` is already created by
// 2024_01_01_000011_create_api_settings_table.php, and the App\Models\Webhook
// model matches THAT shape (tenant_id / url / event / events / headers /
// is_active / retry_count).
//
// This migration originally issued a second Schema::create('webhooks') with a
// conflicting shape, which made `migrate:fresh` fail outright with
// "Base table or view already exists". It is now additive: it contributes the
// extra columns the webhook delivery services need (api_key_id, secret,
// retry_limit, last_triggered_status, soft deletes) on top of the existing
// table, and each column is added only if it is not already present.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhooks')) {
            // Defensive: if the earlier migration is ever removed, still
            // provide a usable table matching the Webhook model.
            Schema::create('webhooks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->text('url');
                $table->string('event', 100)->nullable();
                $table->json('events')->nullable();
                $table->json('headers')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('retry_count')->default(3);
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('webhooks', function (Blueprint $table) {
            if (!Schema::hasColumn('webhooks', 'api_key_id')) {
                $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            }
            if (!Schema::hasColumn('webhooks', 'secret')) {
                $table->string('secret', 255)->nullable();
            }
            if (!Schema::hasColumn('webhooks', 'retry_limit')) {
                $table->integer('retry_limit')->default(5);
            }
            if (!Schema::hasColumn('webhooks', 'last_triggered_status')) {
                $table->string('last_triggered_status')->nullable();
            }
            if (!Schema::hasColumn('webhooks', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            if (Schema::hasColumn('webhooks', 'api_key_id')) {
                $table->dropConstrainedForeignId('api_key_id');
            }
            foreach (['secret', 'retry_limit', 'last_triggered_status', 'deleted_at'] as $column) {
                if (Schema::hasColumn('webhooks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
