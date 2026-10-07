<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'superadmin_dashboard', 'display_name' => 'Dashboard', 'module' => 'Superadmin Main'],
            ['name' => 'superadmin_courier_workspaces', 'display_name' => 'Courier Workspaces', 'module' => 'Superadmin Couriers'],
            ['name' => 'superadmin_courier_customers', 'display_name' => 'Customers', 'module' => 'Superadmin Couriers'],
            ['name' => 'superadmin_staff_employees', 'display_name' => 'Employees', 'module' => 'Superadmin Staff'],
            ['name' => 'superadmin_staff_roles', 'display_name' => 'Roles & Permissions', 'module' => 'Superadmin Staff'],
            ['name' => 'superadmin_staff_branches', 'display_name' => 'Branches', 'module' => 'Superadmin Staff'],
            ['name' => 'superadmin_logistics', 'display_name' => 'Manifests & Checking', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_crm_chat', 'display_name' => 'Chat', 'module' => 'Superadmin CRM'],
            ['name' => 'superadmin_crm_messaging', 'display_name' => 'Email / Messaging', 'module' => 'Superadmin CRM'],
            ['name' => 'superadmin_payments', 'display_name' => 'Invoices & Payments', 'module' => 'Superadmin Finances'],
            ['name' => 'superadmin_courier_billing', 'display_name' => 'Courier Billing', 'module' => 'Superadmin Finances'],
            ['name' => 'superadmin_subscription_plans', 'display_name' => 'Subscription Plans', 'module' => 'Superadmin Finances'],
            ['name' => 'superadmin_expense_income', 'display_name' => 'Expense & Income', 'module' => 'Superadmin Finances'],
            ['name' => 'superadmin_profit_losses', 'display_name' => 'Profit & Losses', 'module' => 'Superadmin Finances'],
            ['name' => 'superadmin_billing_rates', 'display_name' => 'Billing Rates', 'module' => 'Superadmin Finances'],
            ['name' => 'superadmin_settings', 'display_name' => 'System Config', 'module' => 'Superadmin Configuration'],
            ['name' => 'superadmin_maintenance', 'display_name' => 'Backup / Restore / Sync', 'module' => 'Superadmin Configuration'],
            ['name' => 'superadmin_reports', 'display_name' => 'Reports', 'module' => 'Superadmin System'],
            ['name' => 'superadmin_audit', 'display_name' => 'Audit Trail', 'module' => 'Superadmin System'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        Role::where('tenant_id', null)
            ->where('name', 'super_admin')
            ->get()
            ->each(function (Role $role) {
                $role->permissions()->syncWithoutDetaching(Permission::where('name', 'like', 'superadmin_%')->pluck('id'));
            });
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'superadmin_%')->delete();
    }
};
