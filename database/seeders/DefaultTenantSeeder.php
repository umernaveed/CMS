<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\TenantSubscription;
use App\Models\Outlet;
use App\Models\PackageType;
use App\Models\Role;
use App\Models\DeliveryArea;
use App\Models\DeliveryTimeSlot;
use App\Models\WeightRate;

class DefaultTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tenant = Tenant::firstOrCreate(['slug' => 'demo-courier-company'], [
            'company_name' => 'Demo Courier Company',
            'subdomain' => 'demo',
            'custom_domain' => 'demo.courierjamaica.com',
            'email' => 'info@demo.courierjamaica.com',
            'phone' => '+1-876-555-0100',
            'currency' => 'JMD',
            'timezone' => 'America/Jamaica',
            'address' => 'Kingston, Jamaica',
            'city' => 'Kingston',
            'parish' => 'Kingston',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(30),
            'subscription_ends_at' => now()->addYear(),
        ]);

        $outlet = Outlet::firstOrCreate(['tenant_id' => $tenant->id, 'outlet_code' => 'MAIN'], [
            'name' => 'Kingston Main Office',
            'short_name' => 'Kingston',
            'email' => 'info@demo.courierjamaica.com',
            'phone' => '+1-876-555-0100',
            'address' => 'Kingston, Jamaica',
            'city' => 'Kingston',
            'parish' => 'Kingston',
            'manager_name' => 'Tenant Admin',
            'is_main' => true,
            'is_active' => true,
        ]);

        $superadmin = User::firstOrCreate(['email' => 'superadmin@courierjamaica.com'], [
            'tenant_id' => null,
            'full_name' => 'System Admin',
            'password' => bcrypt('123456'),
            'user_type' => 'super_admin',
            'is_active' => true,
        ]);

        $tenantAdmin = User::firstOrCreate(['email' => 'admin@demo.courierjamaica.com'], [
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'full_name' => 'Tenant Admin',
            'password' => bcrypt('123456'),
            'user_type' => 'tenant_admin',
            'is_active' => true,
        ]);

        $outletStaff = User::firstOrCreate(['email' => 'outlet@demo.courierjamaica.com'], [
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'full_name' => 'Outlet Staff',
            'password' => bcrypt('123456'),
            'user_type' => 'staff',
            'is_active' => true,
        ]);

        $customerUser = User::firstOrCreate(['email' => 'customer@demo.courierjamaica.com'], [
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'mailbox' => 'CUST-1001',
            'full_name' => 'John Customer',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $superadmin->roles()->syncWithoutDetaching(Role::where('name', 'super_admin')->pluck('id'));
        $tenantAdmin->roles()->syncWithoutDetaching(Role::where('name', 'tenant_admin')->pluck('id'));
        $outletStaff->roles()->syncWithoutDetaching(Role::where('name', 'staff')->pluck('id'));
        $customerUser->roles()->syncWithoutDetaching(Role::where('name', 'customer')->pluck('id'));

        Customer::firstOrCreate(['tenant_id' => $tenant->id, 'mailbox' => 'CUST-1001'], [
            'tenant_id' => $tenant->id,
            'user_id' => $customerUser->id,
            'outlet_id' => $outlet->id,
            'credit_limit' => 0,
            'current_balance' => 0,
            'total_points' => 0,
            'total_referral_points' => 0,
            'is_active' => true,
        ]);

        TenantSubscription::firstOrCreate(['tenant_id' => $tenant->id], [
            'tenant_id' => $tenant->id,
            'plan_id' => 2,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        foreach (['General Merchandise', 'Documents', 'Electronics', 'Clothing'] as $name) {
            PackageType::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name], [
                'code' => strtoupper(substr($name, 0, 3)),
                'is_active' => true,
            ]);
        }

        foreach ([
            ['min_weight' => 0, 'max_weight' => 2, 'price_jmd' => 900, 'express_price_jmd' => 1300],
            ['min_weight' => 2.01, 'max_weight' => 5, 'price_jmd' => 1600, 'express_price_jmd' => 2200],
            ['min_weight' => 5.01, 'max_weight' => 10, 'price_jmd' => 2800, 'express_price_jmd' => 3600],
            ['min_weight' => 10.01, 'max_weight' => 999, 'price_jmd' => 4500, 'express_price_jmd' => 5800],
        ] as $rate) {
            WeightRate::firstOrCreate([
                'tenant_id' => $tenant->id,
                'min_weight' => $rate['min_weight'],
                'max_weight' => $rate['max_weight'],
                'freight_type' => 'air',
            ], array_merge($rate, ['tenant_id' => $tenant->id, 'freight_type' => 'air', 'is_active' => true]));
        }

        foreach ([
            ['name' => 'Kingston Metro', 'base_cost' => 700],
            ['name' => 'Portmore', 'base_cost' => 950],
            ['name' => 'Spanish Town', 'base_cost' => 1200],
        ] as $areaData) {
            $area = DeliveryArea::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $areaData['name']], [
                'base_cost' => $areaData['base_cost'],
                'per_km_cost' => 0,
                'is_active' => true,
            ]);

            foreach ([1, 2, 3, 4, 5] as $day) {
                DeliveryTimeSlot::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'area_id' => $area->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '14:00:00',
                ], [
                    'max_requests' => 20,
                    'is_active' => true,
                ]);
            }
        }
    }
}

