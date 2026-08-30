<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NOTE: `webhook_logs` is already created by
// 2024_01_01_000011_create_api_settings_table.php, and the App\Models\WebhookLog
// model matches THAT shape (webhook_id / event / payload / status /
// response_code / response_body / retry_count / triggered_at).
//
// This migration originally issued a second Schema::create('webhook_logs')
// with a conflicting shape, breaking `migrate:fresh`. It is now additive,
// contributing the delivery-tracking columns the webhook services use.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
                $table->string('event', 100)->nullable();
                $table->json('payload')->nullable();
                $table->string('status', 20)->default('scheduled');
                $table->string('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->integer('retry_count')->default(0);
                $table->timestamp('triggered_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('webhook_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_logs', 'delivery_id')) {
                $table->foreignId('delivery_id')->nullable()->constrained('webhook_deliveries')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('webhook_logs', 'message')) {
                $table->text('message')->nullable();
            }
            if (!Schema::hasColumn('webhook_logs', 'retry_at')) {
                $table->timestamp('retry_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            if (Schema::hasColumn('webhook_logs', 'delivery_id')) {
                $table->dropConstrainedForeignId('delivery_id');
            }
            foreach (['message', 'retry_at'] as $column) {
                if (Schema::hasColumn('webhook_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
