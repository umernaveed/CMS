<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'superadmin_logistics')->update([
            'display_name' => 'All Logistics',
            'module' => 'Superadmin Logistics',
        ]);
        $permissions = [
            ['name' => 'superadmin_logistics_overview', 'display_name' => 'Overview', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_logistics_manifests', 'display_name' => 'Manifests', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_logistics_bulk_check_in', 'display_name' => 'Bulk Check-In', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_logistics_bulk_upload', 'display_name' => 'Bulk Upload', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_logistics_packages', 'display_name' => 'Packages / Unknown Room', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_logistics_rates', 'display_name' => 'Rates & Pricing', 'module' => 'Superadmin Logistics'],
            ['name' => 'superadmin_logistics_invoices', 'display_name' => 'Invoices', 'module' => 'Superadmin Logistics'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        $childPermissionIds = Permission::whereIn('name', collect($permissions)->pluck('name'))->pluck('id');

        Role::whereHas('permissions', fn ($query) => $query->where('name', 'superadmin_logistics'))
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($childPermissionIds));

        Role::whereNull('tenant_id')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($childPermissionIds));
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'superadmin_logistics_overview',
            'superadmin_logistics_manifests',
            'superadmin_logistics_bulk_check_in',
            'superadmin_logistics_bulk_upload',
            'superadmin_logistics_packages',
            'superadmin_logistics_rates',
            'superadmin_logistics_invoices',
        ])->delete();
    }
};