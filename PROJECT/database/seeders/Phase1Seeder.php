<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Communication;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class Phase1Seeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first() ?? Tenant::create([
            'name' => 'Default Tenant',
            'slug' => 'default-tenant',
            'is_active' => true,
        ]);

        $user = $tenant->users()->first() ?? User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);

        // Create sample customers
        for ($i = 1; $i <= 10; $i++) {
            Customer::create([
                'tenant_id' => $tenant->id,
                'name' => "Customer {$i}",
                'email' => "customer{$i}@example.com",
                'phone' => "+1234567{$i}90",
                'customer_type' => $i % 3 === 0 ? 'corporate' : 'individual',
                'country' => 'USA',
                'city' => 'New York',
                'status' => 'active',
                'is_active' => true,
            ]);
        }

        // Create sample leads
        $sources = ['website', 'referral', 'email', 'phone', 'social_media'];
        for ($i = 1; $i <= 15; $i++) {
            Lead::create([
                'tenant_id' => $tenant->id,
                'name' => "Lead {$i}",
                'email' => "lead{$i}@example.com",
                'phone' => "+9876543{$i}21",
                'company' => "Company {$i}",
                'source' => $sources[$i % count($sources)],
                'priority' => ['low', 'medium', 'high', 'urgent'][$i % 4],
                'status' => ['new', 'contacted', 'qualified'][$i % 3],
                'assigned_to' => $user->id,
                'conversion_probability' => rand(10, 100),
            ]);
        }

        // Create sample communications
        $customers = Customer::where('tenant_id', $tenant->id)->get();
        foreach ($customers as $customer) {
            for ($j = 0; $j < 3; $j++) {
                Communication::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'type' => ['email', 'phone', 'sms', 'meeting'][$j % 4],
                    'subject' => "Communication about travel package",
                    'message' => "This is a sample communication #{$j}",
                    'created_by' => $user->id,
                    'status' => 'sent',
                    'sent_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }

        // Create sample tasks
        for ($i = 1; $i <= 10; $i++) {
            Task::create([
                'tenant_id' => $tenant->id,
                'title' => "Task {$i}",
                'type' => ['task', 'followup', 'reminder', 'meeting'][$i % 4],
                'status' => $i % 3 === 0 ? 'completed' : 'open',
                'priority' => ['low', 'medium', 'high', 'urgent'][$i % 4],
                'assigned_to' => $user->id,
                'due_date' => now()->addDays(rand(1, 30)),
                'completed_at' => $i % 3 === 0 ? now()->subDays(rand(1, 10)) : null,
            ]);
        }

        echo "✅ Phase 1 seed data created\n";
    }
}
