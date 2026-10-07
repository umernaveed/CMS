<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'subscription_trial_days'],
            ['value' => '0', 'group' => 'payment']
        );

        SystemSetting::updateOrCreate(
            ['key' => 'subscription_payment_gateways'],
            ['value' => 'paypal,dime,amber_pay', 'group' => 'payment']
        );

        $activeGateway = SystemSetting::where('key', 'subscription_payment_gateway')->value('value');
        if (in_array($activeGateway, ['test_gateway', 'manual', 'free_trial', null, ''], true)) {
            SystemSetting::updateOrCreate(
                ['key' => 'subscription_payment_gateway'],
                ['value' => 'paypal', 'group' => 'payment']
            );
        }
    }

    public function down(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'subscription_trial_days'],
            ['value' => '14', 'group' => 'payment']
        );

        SystemSetting::updateOrCreate(
            ['key' => 'subscription_payment_gateways'],
            ['value' => 'test_gateway,paypal,dime,amber_pay,manual', 'group' => 'payment']
        );
    }
};
