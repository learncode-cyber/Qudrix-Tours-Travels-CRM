<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Entry point for `php artisan db:seed` and `migrate --seed`.
//
// This seeds ESSENTIAL data only — the system roles the RBAC layer needs,
// and a first tenant + administrator so the application can be logged into.
// It deliberately seeds NO demo customers, leads, bookings or revenue:
// Directive §27 forbids fake data in production features, and a dashboard
// showing invented revenue is exactly that. Demo data lives in
// Phase1Seeder, which an operator must run explicitly.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $tenant = Tenant::firstOrCreate(
            ['slug' => env('SEED_TENANT_SLUG', 'qudrix')],
            [
                'name' => env('SEED_TENANT_NAME', 'Qudrix Travel'),
                'email' => env('SEED_ADMIN_EMAIL', 'admin@qudrix.local'),
                'timezone' => env('APP_TIMEZONE', 'UTC'),
                'currency' => 'USD',
                'is_active' => true,
                'plan' => 'enterprise',
            ],
        );

        $adminEmail = env('SEED_ADMIN_EMAIL', 'admin@qudrix.local');

        // The password comes from the environment. There is deliberately no
        // hardcoded default: shipping a known admin password is how seeded
        // installs get compromised. If SEED_ADMIN_PASSWORD is unset a random
        // one is generated and printed once, so it is never guessable.
        $password = env('SEED_ADMIN_PASSWORD');
        $generated = false;
        if (blank($password)) {
            $password = bin2hex(random_bytes(12));
            $generated = true;
        }

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'tenant_id' => $tenant->id,
                'name' => env('SEED_ADMIN_NAME', 'Administrator'),
                'password' => Hash::make($password),
                'is_active' => true,
                'status' => 'active',
            ],
        );

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin && !$admin->roles()->where('roles.id', $superAdmin->id)->exists()) {
            $admin->roles()->attach($superAdmin->id, ['tenant_id' => $tenant->id]);
        }

        $this->command?->info("Seeded tenant '{$tenant->name}' and administrator {$adminEmail}.");

        if ($generated) {
            $this->command?->warn("Generated admin password (shown once): {$password}");
            $this->command?->warn('Set SEED_ADMIN_PASSWORD in .env to control this, and change it after first login.');
        }
    }
}
