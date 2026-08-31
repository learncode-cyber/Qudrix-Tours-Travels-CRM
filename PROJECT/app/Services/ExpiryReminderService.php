<?php

namespace App\Services;

use App\Models\BookingTraveler;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Support\Collection;

// Scans visa and passport expiry dates and creates a Reminder for
// anything expiring within the lookahead window — the "expiry/reminder
// system" the directive asks for. Runs per-tenant (there is no global
// user to notify) and is idempotent: it never creates a second pending
// reminder for the same expiring record.
class ExpiryReminderService
{
    // reminders.user_id is NOT NULL, but a visa application's assigned_to
    // and a traveler have no natural owning user — fall back to the
    // tenant's earliest-created (i.e. the seeded admin/owner) user so the
    // reminder still lands somewhere real, per tenant, cached per run.
    private array $defaultUserCache = [];

    private function defaultUserId(int $tenantId): ?int
    {
        if (!array_key_exists($tenantId, $this->defaultUserCache)) {
            $this->defaultUserCache[$tenantId] = User::where('tenant_id', $tenantId)
                ->orderBy('id')
                ->value('id');
        }

        return $this->defaultUserCache[$tenantId];
    }

    public function checkAll(int $lookaheadDays = 90): array
    {
        $created = ['visa' => 0, 'passport' => 0];

        foreach (Tenant::pluck('id') as $tenantId) {
            $created['visa'] += $this->checkVisaExpiries($tenantId, $lookaheadDays)->count();
            $created['passport'] += $this->checkPassportExpiries($tenantId, $lookaheadDays)->count();
        }

        return $created;
    }

    public function checkVisaExpiries(int $tenantId, int $lookaheadDays = 90): Collection
    {
        $applications = VisaApplication::where('tenant_id', $tenantId)
            ->whereNotNull('expiry_date')
            ->whereIn('status', ['approved'])
            ->where('expiry_date', '<=', now()->addDays($lookaheadDays))
            ->where('expiry_date', '>=', now())
            ->get();

        $created = collect();
        $defaultUserId = $this->defaultUserId($tenantId);
        if (!$defaultUserId) {
            return $created;
        }

        foreach ($applications as $application) {
            if ($this->hasPendingReminder(VisaApplication::class, $application->id)) {
                continue;
            }

            $created->push(Reminder::create([
                'tenant_id' => $tenantId,
                'user_id' => $application->assigned_to ?? $defaultUserId,
                'remindable_type' => VisaApplication::class,
                'remindable_id' => $application->id,
                'title' => "Visa #{$application->visa_number} for destination {$application->destination_country} expires " . $application->expiry_date->toDateString(),
                'remind_at' => $application->expiry_date->copy()->subDays(min(30, $lookaheadDays)),
                'status' => 'pending',
            ]));
        }

        return $created;
    }

    public function checkPassportExpiries(int $tenantId, int $lookaheadDays = 90): Collection
    {
        $travelers = BookingTraveler::whereHas('booking', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('passport_expiry')
            ->where('passport_expiry', '<=', now()->addDays($lookaheadDays))
            ->where('passport_expiry', '>=', now())
            ->get();

        $created = collect();
        $defaultUserId = $this->defaultUserId($tenantId);
        if (!$defaultUserId) {
            return $created;
        }

        foreach ($travelers as $traveler) {
            if ($this->hasPendingReminder(BookingTraveler::class, $traveler->id)) {
                continue;
            }

            $created->push(Reminder::create([
                'tenant_id' => $tenantId,
                'user_id' => $defaultUserId,
                'remindable_type' => BookingTraveler::class,
                'remindable_id' => $traveler->id,
                'title' => "Passport for {$traveler->getFullName()} expires " . $traveler->passport_expiry->toDateString(),
                'remind_at' => $traveler->passport_expiry->copy()->subDays(min(30, $lookaheadDays)),
                'status' => 'pending',
            ]));
        }

        return $created;
    }

    private function hasPendingReminder(string $remindableType, int $remindableId): bool
    {
        return Reminder::where('remindable_type', $remindableType)
            ->where('remindable_id', $remindableId)
            ->where('status', 'pending')
            ->exists();
    }
}
