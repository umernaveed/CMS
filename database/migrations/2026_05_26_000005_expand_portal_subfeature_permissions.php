<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'view_dashboard', 'display_name' => 'Courier Workspace', 'module' => 'Couriers'],
            ['name' => 'manage_customers', 'display_name' => 'Customers', 'module' => 'Couriers'],
            ['name' => 'manage_authorized_users', 'display_name' => 'Authorized Users', 'module' => 'CRM'],
            ['name' => 'manage_crm_emails', 'display_name' => 'Email / Messaging', 'module' => 'CRM'],
            ['name' => 'manage_tickets', 'display_name' => 'Chat', 'module' => 'CRM'],
            ['name' => 'manage_staff', 'display_name' => 'Employees', 'module' => 'Staff'],
            ['name' => 'manage_roles', 'display_name' => 'Roles & Permissions', 'module' => 'Staff'],
            ['name' => 'manage_branches', 'display_name' => 'Branches', 'module' => 'Staff'],
            ['name' => 'manage_packages', 'display_name' => 'Packages', 'module' => 'Packages'],
            ['name' => 'manage_pre_alerts', 'display_name' => 'Pre-Alerts', 'module' => 'Packages'],
            ['name' => 'manage_purchase_requests', 'display_name' => 'Purchase Requests', 'module' => 'Packages'],
            ['name' => 'manage_package_exceptions', 'display_name' => 'Aging / Uncollected Packages', 'module' => 'Packages'],
            ['name' => 'manage_warehouse', 'display_name' => 'Warehouse / Unmanifest', 'module' => 'Logistics'],
            ['name' => 'manage_manifests', 'display_name' => 'Manifests', 'module' => 'Logistics'],
            ['name' => 'manage_delivery', 'display_name' => 'Delivery Requests & Locations', 'module' => 'Logistics'],
            ['name' => 'manage_invoices', 'display_name' => 'Invoices', 'module' => 'Finance'],
            ['name' => 'manage_payments', 'display_name' => 'Payments', 'module' => 'Finance'],
            ['name' => 'manage_transactions', 'display_name' => 'Income / Expense & Transactions', 'module' => 'Finance'],
            ['name' => 'manage_billing', 'display_name' => 'Courier Bills', 'module' => 'Finance'],
            ['name' => 'view_reports', 'display_name' => 'Reports / Profit & Loss', 'module' => 'Finance'],
            ['name' => 'manage_settings', 'display_name' => 'General Settings', 'module' => 'System Management'],
            ['name' => 'manage_rates', 'display_name' => 'Rates Management', 'module' => 'System Management'],
            ['name' => 'manage_customs_rates', 'display_name' => 'Customs Rates', 'module' => 'System Management'],
            ['name' => 'manage_notifications', 'display_name' => 'Notifications', 'module' => 'System Management'],
            ['name' => 'manage_system', 'display_name' => 'Package Status & Data Tools', 'module' => 'System Management'],
            ['name' => 'manage_activity_logs', 'display_name' => 'Activity Log', 'module' => 'System Management'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'module' => $permission['module'],
                ]
            );
        }

        $allPermissions = Permission::pluck('id');
        Role::whereIn('name', ['super_admin', 'tenant_admin'])->get()->each(function (Role $role) use ($allPermissions) {
            $role->permissions()->syncWithoutDetaching($allPermissions);
        });

        $staffPermissions = Permission::whereIn('name', [
            'view_dashboard',
            'manage_customers',
            'manage_authorized_users',
            'manage_tickets',
            'manage_packages',
            'manage_pre_alerts',
            'manage_purchase_requests',
            'manage_package_exceptions',
            'manage_warehouse',
            'manage_manifests',
            'manage_delivery',
            'manage_invoices',
            'manage_payments',
            'manage_transactions',
            'manage_billing',
            'view_reports',
        ])->pluck('id');

        Role::where('name', 'staff')->get()->each(function (Role $role) use ($staffPermissions) {
            $role->permissions()->syncWithoutDetaching($staffPermissions);
        });
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'manage_authorized_users',
            'manage_crm_emails',
            'manage_pre_alerts',
            'manage_purchase_requests',
            'manage_package_exceptions',
            'manage_warehouse',
            'manage_billing',
            'manage_rates',
            'manage_customs_rates',
        ])->delete();
    }
};
