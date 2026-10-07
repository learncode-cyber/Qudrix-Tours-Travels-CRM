<?php
namespace App\Console\Commands;

use App\Models\Installment;
use Illuminate\Console\Command;

/**
 * MASTER_PROJECT_AUDIT.md P1 (installment management). Runs across every
 * tenant (unlike the controller endpoint, which is tenant-scoped to the
 * authenticated request) — intended for the scheduler, not direct HTTP use.
 */
class FlagOverdueInstallments extends Command
{
    protected $signature = 'installments:flag-overdue';
    protected $description = 'Mark pending installments whose due date has passed as overdue, across all tenants';

    public function handle(): int
    {
        $count = Installment::where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $this->info("Flagged {$count} installment(s) as overdue.");

        return self::SUCCESS;
    }
}
