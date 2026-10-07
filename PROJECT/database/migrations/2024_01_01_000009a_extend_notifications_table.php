<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 7: notifications (Phase 2) only supported an internal staff
 * recipient (user_id) with no channel or delivery-status tracking.
 * Booking confirmations, visa status updates, and payment reminders are
 * customer-facing, not staff-facing, and need to record which channel
 * was attempted and whether it actually delivered — not just "created".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->cascadeOnDelete();
            $table->string('channel')->default('in_app'); // in_app, email, sms, whatsapp, telegram
            $table->string('delivery_status')->default('delivered'); // delivered, blocked, failed
            $table->string('delivery_detail')->nullable(); // reason when blocked/failed
            $table->string('related_entity_type')->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn([
                'customer_id', 'channel', 'delivery_status',
                'delivery_detail', 'related_entity_type', 'related_entity_id',
            ]);
        });
    }
};
