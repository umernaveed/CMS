<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpandCourierPermissions extends Migration
{
    public function up()
    {
        $permissions = [
            ['name' => 'view_dashboard', 'display_name' => 'Dashboard', 'module' => 'Dashboard'],
            ['name' => 'manage_activity_logs', 'display_name' => 'Activity Logs', 'module' => 'Activity Logs'],
            ['name' => 'manage_branches', 'display_name' => 'Branches', 'module' => 'Branches'],
            ['name' => 'manage_carriers', 'display_name' => 'Carriers', 'module' => 'Carriers'],
            ['name' => 'manage_customers', 'display_name' => 'Customers', 'module' => 'Customers'],
            ['name' => 'manage_delivery', 'display_name' => 'Delivery Requests', 'module' => 'Delivery Requests'],
            ['name' => 'manage_invoices', 'display_name' => 'Invoices', 'module' => 'Invoices'],
            ['name' => 'manage_manifests', 'display_name' => 'Manifests', 'module' => 'Manifests'],
            ['name' => 'manage_notifications', 'display_name' => 'Notifications', 'module' => 'Notifications'],
            ['name' => 'manage_packages', 'display_name' => 'Packages', 'module' => 'Packages'],
            ['name' => 'manage_payments', 'display_name' => 'Payments', 'module' => 'Payments'],
            ['name' => 'view_reports', 'display_name' => 'Reports', 'module' => 'Reports'],
            ['name' => 'manage_roles', 'display_name' => 'Roles', 'module' => 'Roles'],
            ['name' => 'manage_settings', 'display_name' => 'Settings', 'module' => 'Settings'],
            ['name' => 'manage_staff', 'display_name' => 'Staff', 'module' => 'Staff'],
            ['name' => 'manage_system', 'display_name' => 'System', 'module' => 'System'],
            ['name' => 'manage_tickets', 'display_name' => 'Tickets', 'module' => 'Tickets'],
            ['name' => 'manage_transactions', 'display_name' => 'Transactions', 'module' => 'Transactions'],
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

        Role::whereIn('name', ['super_admin', 'tenant_admin'])->get()->each(function ($role) {
            $role->permissions()->syncWithoutDetaching(Permission::pluck('id'));
        });

        Role::where('name', 'staff')->get()->each(function ($role) {
            $role->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', [
                    'view_dashboard',
                    'manage_customers',
                    'manage_packages',
                    'manage_invoices',
                    'manage_payments',
                    'manage_manifests',
                    'manage_delivery',
                    'view_reports',
                    'manage_tickets',
                    'manage_transactions',
                ])->pluck('id')
            );
        });

        $systemStaffRoleIds = Role::whereNull('tenant_id')->where('name', 'staff')->pluck('id');
        if ($systemStaffRoleIds->isNotEmpty()) {
            $usersWithCustomRoles = DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->whereNotNull('roles.tenant_id')
                ->pluck('user_roles.user_id')
                ->unique();

            if ($usersWithCustomRoles->isNotEmpty()) {
                DB::table('user_roles')
                    ->whereIn('role_id', $systemStaffRoleIds)
                    ->whereIn('user_id', $usersWithCustomRoles)
                    ->delete();
            }
        }
    }

    public function down()
    {
        Permission::whereIn('name', [
            'manage_activity_logs',
            'manage_branches',
            'manage_carriers',
            'manage_notifications',
            'manage_roles',
            'manage_staff',
            'manage_system',
            'manage_tickets',
            'manage_transactions',
        ])->delete();
    }
}
