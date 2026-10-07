<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
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
            ['name' => 'superadmin_dashboard', 'display_name' => 'Dashboard', 'module' => 'System Admin Main'],
            ['name' => 'superadmin_courier_workspaces', 'display_name' => 'View Courier Workspaces', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_create', 'display_name' => 'Add Courier', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_edit', 'display_name' => 'Edit Couriers', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_delete', 'display_name' => 'Delete Couriers', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_open_portal', 'display_name' => 'Open Courier Portal', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_credit', 'display_name' => 'Courier Credits & Wallet', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_customers', 'display_name' => 'Customers', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_staff_employees', 'display_name' => 'Employees', 'module' => 'System Admin Staff'],
            ['name' => 'superadmin_staff_roles', 'display_name' => 'Roles & Permissions', 'module' => 'System Admin Staff'],
            ['name' => 'superadmin_staff_branches', 'display_name' => 'Branches', 'module' => 'System Admin Staff'],
            ['name' => 'superadmin_logistics', 'display_name' => 'All Logistics', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_overview', 'display_name' => 'Overview', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_manifests', 'display_name' => 'Manifests', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_bulk_check_in', 'display_name' => 'Bulk Check-In', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_bulk_upload', 'display_name' => 'Bulk Upload', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_packages', 'display_name' => 'Packages / Unknown Room', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_rates', 'display_name' => 'Rates & Pricing', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_logistics_invoices', 'display_name' => 'Invoices', 'module' => 'System Admin Logistics'],
            ['name' => 'superadmin_crm_chat', 'display_name' => 'Chat', 'module' => 'System Admin CRM'],
            ['name' => 'superadmin_crm_messaging', 'display_name' => 'Email / Messaging', 'module' => 'System Admin CRM'],
            ['name' => 'superadmin_payments', 'display_name' => 'Invoices & Payments', 'module' => 'System Admin Finances'],
            ['name' => 'superadmin_courier_billing', 'display_name' => 'Courier Billing', 'module' => 'System Admin Finances'],
            ['name' => 'superadmin_subscription_plans', 'display_name' => 'Subscription Plans', 'module' => 'System Admin Finances'],
            ['name' => 'superadmin_expense_income', 'display_name' => 'Expense & Income', 'module' => 'System Admin Finances'],
            ['name' => 'superadmin_profit_losses', 'display_name' => 'Profit & Losses', 'module' => 'System Admin Finances'],
            ['name' => 'superadmin_billing_rates', 'display_name' => 'Billing Rates', 'module' => 'System Admin Finances'],
            ['name' => 'superadmin_settings', 'display_name' => 'All System Config', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_general', 'display_name' => 'System Settings', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_registration', 'display_name' => 'Registration', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_email', 'display_name' => 'Email', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_sms', 'display_name' => 'SMS', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_shipping', 'display_name' => 'Shipping', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_payment', 'display_name' => 'Payments', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_master_tech', 'display_name' => 'Master Tech Codes', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_references', 'display_name' => 'Reference Data', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_maintenance', 'display_name' => 'Backup / Restore / Sync', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_reports', 'display_name' => 'Reports', 'module' => 'System Admin System'],
            ['name' => 'superadmin_audit', 'display_name' => 'Audit Trail', 'module' => 'System Admin System'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        $superadmin = Role::firstOrCreate(['tenant_id' => null, 'name' => 'super_admin'], ['display_name' => 'System Administrator', 'is_system' => true]);
        $tenantAdmin = Role::firstOrCreate(['tenant_id' => null, 'name' => 'tenant_admin'], ['display_name' => 'Tenant Administrator', 'is_system' => true]);
        $staff = Role::firstOrCreate(['tenant_id' => null, 'name' => 'staff'], ['display_name' => 'Staff Member', 'is_system' => true]);
        $customer = Role::firstOrCreate(['tenant_id' => null, 'name' => 'customer'], ['display_name' => 'Customer', 'is_system' => true]);

        $superadmin->permissions()->sync(Permission::pluck('id'));

        $courierPermissions = Permission::whereIn('name', [
            'view_dashboard', 'manage_packages', 'manage_customers', 'manage_invoices',
            'manage_authorized_users', 'manage_crm_emails', 'manage_tickets',
            'manage_pre_alerts', 'manage_purchase_requests', 'manage_package_exceptions',
            'manage_warehouse', 'manage_payments', 'manage_billing', 'manage_transactions',
            'manage_branches', 'manage_manifests', 'manage_delivery', 'view_reports',
            'manage_settings', 'manage_rates', 'manage_customs_rates', 'manage_notifications',
            'manage_roles', 'manage_staff', 'manage_system', 'manage_activity_logs',
        ])->pluck('id');
        $tenantAdmin->permissions()->sync($courierPermissions);
        $staff->permissions()->sync($courierPermissions);

        $customer->permissions()->sync(Permission::whereIn('name', [
            'view_dashboard', 'manage_packages', 'manage_invoices',
        ])->pluck('id'));
    }
}
