<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_PROJECT_AUDIT.md P1: no i18n architecture existed at all beyond
 * a single static `config/app.php` locale and the tenants.language column
 * (Phase 18 SaaS readiness — reused here, not duplicated). This adds the
 * one real missing piece: a per-user preferred locale (what the CRM
 * staff/agent sees), plus locale variants for customer-facing notification
 * templates, supporting bn/en/ar to match the stated scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('avatar_url');
        });

        // Customer-facing notification templates were single-language only
        // (unique per tenant+event_key+channel) — add locale as part of the
        // uniqueness key so the same event can have bn/en/ar variants.
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('channel');
        });
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'event_key', 'channel']);
            $table->unique(['tenant_id', 'event_key', 'channel', 'locale'], 'notif_template_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique('notif_template_locale_unique');
            $table->unique(['tenant_id', 'event_key', 'channel']);
            $table->dropColumn('locale');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
