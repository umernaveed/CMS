<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'superadmin_courier_workspaces_create', 'display_name' => 'Add Courier', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_edit', 'display_name' => 'Edit Couriers', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_delete', 'display_name' => 'Delete Couriers', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_open_portal', 'display_name' => 'Open Courier Portal', 'module' => 'System Admin Couriers'],
            ['name' => 'superadmin_courier_workspaces_credit', 'display_name' => 'Courier Credits & Wallet', 'module' => 'System Admin Couriers'],
        ];

        Permission::where('name', 'superadmin_courier_workspaces')->update([
            'display_name' => 'View Courier Workspaces',
            'module' => 'System Admin Couriers',
        ]);

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        $childPermissionIds = Permission::whereIn('name', collect($permissions)->pluck('name'))->pluck('id');

        Role::whereHas('permissions', fn ($query) => $query->where('name', 'superadmin_courier_workspaces'))
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($childPermissionIds));
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'superadmin_courier_workspaces_create',
            'superadmin_courier_workspaces_edit',
            'superadmin_courier_workspaces_delete',
            'superadmin_courier_workspaces_open_portal',
            'superadmin_courier_workspaces_credit',
        ])->delete();

        Permission::where('name', 'superadmin_courier_workspaces')->update([
            'display_name' => 'Courier Workspaces',
            'module' => 'System Admin Couriers',
        ]);
    }
};
