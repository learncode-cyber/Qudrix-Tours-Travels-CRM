<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

// Seeds the system roles RBACMiddleware checks against. Without these the
// permission layer has nothing to authorise against, so this is essential
// data, not demo data.
class RoleSeeder extends Seeder
{
    public const ROLES = [
        [
            'name' => 'super_admin',
            'display_name' => 'Super Administrator',
            'description' => 'Full access to every module and setting.',
            // '*' is the wildcard Role::hasPermission() understands.
            'permissions' => ['*'],
        ],
        [
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Manages operations, staff and settings for the agency.',
            'permissions' => [
                'customers:read', 'customers:create', 'customers:update', 'customers:delete',
                'leads:read', 'leads:create', 'leads:update', 'leads:assign',
                'quotations:read', 'quotations:create', 'quotations:update', 'quotations:approve',
                'bookings:read', 'bookings:create', 'bookings:update', 'bookings:cancel',
                'invoices:read', 'invoices:create', 'payments:read', 'payments:create',
                'visas:read', 'visas:update', 'hajj:manage', 'student_visa:manage',
                'agents:manage', 'hrm:manage', 'marketing:manage', 'reports:read',
                'settings:manage', 'integrations:manage', 'ai:manage', 'webhooks:manage',
            ],
        ],
        [
            'name' => 'sales_manager',
            'display_name' => 'Sales Manager',
            'description' => 'Owns the sales pipeline and approves quotations.',
            'permissions' => [
                'customers:read', 'customers:create', 'customers:update',
                'leads:read', 'leads:create', 'leads:update', 'leads:assign',
                'quotations:read', 'quotations:create', 'quotations:update', 'quotations:approve',
                'bookings:read', 'bookings:create', 'bookings:update',
                'reports:read', 'marketing:manage',
            ],
        ],
        [
            'name' => 'sales_agent',
            'display_name' => 'Sales Agent',
            'description' => 'Works assigned leads and prepares quotations.',
            'permissions' => [
                'customers:read', 'customers:create', 'customers:update',
                'leads:read', 'leads:create', 'leads:update',
                'quotations:read', 'quotations:create', 'quotations:update',
                'bookings:read', 'bookings:create',
            ],
        ],
        [
            'name' => 'operations',
            'display_name' => 'Operations',
            'description' => 'Handles visas, flights, hotels and departures.',
            'permissions' => [
                'customers:read', 'bookings:read', 'bookings:update',
                'visas:read', 'visas:update', 'flights:manage', 'hotels:manage',
                'hajj:manage', 'student_visa:manage', 'reports:read',
            ],
        ],
        [
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'description' => 'Manages invoices, payments and financial reporting.',
            'permissions' => [
                'customers:read', 'bookings:read',
                'invoices:read', 'invoices:create', 'invoices:update',
                'payments:read', 'payments:create', 'expenses:manage',
                'agents:payouts', 'reports:read',
            ],
        ],
        [
            'name' => 'support',
            'display_name' => 'Support Agent',
            'description' => 'Handles support tickets and customer conversations.',
            'permissions' => [
                'customers:read', 'bookings:read',
                'tickets:read', 'tickets:update', 'conversations:read', 'conversations:reply',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::updateOrCreate(
                // System roles are tenant-agnostic (tenant_id null), so a
                // new tenant does not need its own copy.
                ['tenant_id' => null, 'name' => $role['name']],
                [
                    'display_name' => $role['display_name'],
                    'description' => $role['description'],
                    'is_system' => true,
                    'permissions' => $role['permissions'],
                ],
            );
        }
    }
}
