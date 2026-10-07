<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SubscriptionPlansSeeder::class,
            PaymentMethodsSeeder::class,
            SystemSettingsSeeder::class,
            DefaultTenantSeeder::class,
            PackageStatusesSeeder::class,
        ]);
    }
}
