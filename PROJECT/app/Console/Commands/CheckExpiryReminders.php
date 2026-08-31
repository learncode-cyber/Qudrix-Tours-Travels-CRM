<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderService;
use Illuminate\Console\Command;

class CheckExpiryReminders extends Command
{
    protected $signature = 'reminders:check-expiry {--days=90 : Lookahead window in days}';

    protected $description = 'Create reminders for visas and passports expiring within the lookahead window';

    public function handle(ExpiryReminderService $service): int
    {
        $days = (int) $this->option('days');
        $created = $service->checkAll($days);

        $this->info("Created {$created['visa']} visa expiry reminder(s) and {$created['passport']} passport expiry reminder(s).");

        return self::SUCCESS;
    }
}
