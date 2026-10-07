<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for small courier businesses',
                'monthly_price' => 49.00,
                'yearly_price' => 490.00,
                'max_users' => 5,
                'max_customers' => 500,
                'max_packages_month' => 1000,
                'max_outlets' => 1,
                'max_storage_days' => 30,
                'features' => ['features' => ['Up to 5 staff users', 'Up to 500 customers', '1,000 packages/month', '1 outlet/branch', 'Basic reporting', 'Email support']],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Business',
                'description' => 'For growing courier companies',
                'monthly_price' => 99.00,
                'yearly_price' => 990.00,
                'max_users' => 15,
                'max_customers' => 2000,
                'max_packages_month' => 5000,
                'max_outlets' => 3,
                'max_storage_days' => 30,
                'features' => ['features' => ['Up to 15 staff users', 'Up to 2,000 customers', '5,000 packages/month', '3 outlets/branches', 'Advanced reporting', 'Priority support', 'API access']],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Enterprise plan for large organizations',
                'monthly_price' => 149.99,
                'yearly_price' => 1499.99,
                'max_users' => null,
                'max_customers' => null,
                'max_packages_month' => null,
                'max_outlets' => null,
                'max_storage_days' => 30,
                'features' => ['features' => ['Unlimited staff users', 'Unlimited customers', 'Unlimited packages', 'Unlimited outlets', 'Custom reporting', 'Dedicated support', 'Full API access', 'Custom integrations']],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
