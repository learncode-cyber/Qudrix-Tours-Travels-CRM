<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 4 (Travel Operations): daily sweep for visas/passports expiring
// soon. Idempotent — see ExpiryReminderService, safe to also run
// on-demand via `php artisan reminders:check-expiry`.
Schedule::command('reminders:check-expiry')->dailyAt('06:00');
