<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tenant_payments MODIFY payment_method VARCHAR(50) NOT NULL DEFAULT 'test_gateway'");
            DB::statement("ALTER TABLE tenant_payments MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        }

        foreach ([
            ['key' => 'subscription_trial_days', 'value' => '0'],
            ['key' => 'subscription_payment_gateway', 'value' => 'paypal'],
            ['key' => 'subscription_payment_gateways', 'value' => 'paypal,dime,amber_pay'],
            ['key' => 'paypal_checkout_url', 'value' => null],
            ['key' => 'paypal_client_id', 'value' => null],
            ['key' => 'paypal_secret', 'value' => null],
            ['key' => 'dime_checkout_url', 'value' => null],
            ['key' => 'dime_api_key', 'value' => null],
            ['key' => 'amber_pay_checkout_url', 'value' => null],
            ['key' => 'amber_pay_api_key', 'value' => null],
        ] as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => 'payment']
            );
        }
    }

    public function down(): void
    {
        SystemSetting::whereIn('key', [
            'subscription_trial_days',
            'subscription_payment_gateway',
            'subscription_payment_gateways',
            'paypal_checkout_url',
            'paypal_client_id',
            'paypal_secret',
            'dime_checkout_url',
            'dime_api_key',
            'amber_pay_checkout_url',
            'amber_pay_api_key',
        ])->delete();
    }
};
