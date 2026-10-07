<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    public function run()
    {
        $methods = [
            ['tenant_id' => null, 'name' => 'Cash', 'code' => 'CASH', 'is_active' => true],
            ['tenant_id' => null, 'name' => 'Credit Card', 'code' => 'CARD', 'is_active' => true],
            ['tenant_id' => null, 'name' => 'Debit Card', 'code' => 'DEBIT_CARD', 'is_active' => true],
            ['tenant_id' => null, 'name' => 'Bank Transfer', 'code' => 'BANK', 'is_active' => true],
            ['tenant_id' => null, 'name' => 'Online Payment', 'code' => 'ONLINE', 'is_active' => true],
            ['tenant_id' => null, 'name' => 'Reward Points', 'code' => 'REWARDS', 'is_active' => true],
            ['tenant_id' => null, 'name' => 'Referral Points', 'code' => 'REFERRAL', 'is_active' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(['tenant_id' => null, 'code' => $method['code']], $method);
        }
    }
}