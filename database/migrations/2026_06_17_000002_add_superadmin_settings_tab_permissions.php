<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'superadmin_settings_general', 'display_name' => 'System Settings', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_registration', 'display_name' => 'Registration', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_email', 'display_name' => 'Email', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_sms', 'display_name' => 'SMS', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_shipping', 'display_name' => 'Shipping', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_payment', 'display_name' => 'Payments', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_master_tech', 'display_name' => 'Master Tech Codes', 'module' => 'System Admin Configuration'],
            ['name' => 'superadmin_settings_references', 'display_name' => 'Reference Data', 'module' => 'System Admin Configuration'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        Permission::where('name', 'superadmin_settings')->update([
            'display_name' => 'All System Config',
            'module' => 'System Admin Configuration',
        ]);

        Permission::where('name', 'superadmin_maintenance')->update([
            'module' => 'System Admin Configuration',
        ]);

        $childPermissionIds = Permission::whereIn('name', collect($permissions)->pluck('name'))->pluck('id');

        Role::whereHas('permissions', fn ($query) => $query->where('name', 'superadmin_settings'))
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($childPermissionIds));
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'superadmin_settings_general',
            'superadmin_settings_registration',
            'superadmin_settings_email',
            'superadmin_settings_sms',
            'superadmin_settings_shipping',
            'superadmin_settings_payment',
            'superadmin_settings_master_tech',
            'superadmin_settings_references',
        ])->delete();

        Permission::where('name', 'superadmin_settings')->update([
            'display_name' => 'System Config',
            'module' => 'System Admin Configuration',
        ]);
    }
};
