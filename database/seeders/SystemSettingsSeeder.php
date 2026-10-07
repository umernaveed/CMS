<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $settings = [
            ['key' => 'app_name', 'value' => 'Courier SaaS', 'group' => 'general'],
            ['key' => 'app_url', 'value' => 'http://localhost:8000', 'group' => 'general'],
            ['key' => 'company_name', 'value' => 'Courier Jamaica Ltd', 'group' => 'company'],
            ['key' => 'company_address', 'value' => 'Kingston, Jamaica', 'group' => 'company'],
            ['key' => 'company_phone', 'value' => '+1-876-555-0123', 'group' => 'company'],
            ['key' => 'company_email', 'value' => 'info@courierjamaica.com', 'group' => 'company'],
            ['key' => 'smtp_host', 'value' => 'smtp.gmail.com', 'group' => 'email'],
            ['key' => 'smtp_port', 'value' => '587', 'group' => 'email'],
            ['key' => 'smtp_encryption', 'value' => 'tls', 'group' => 'email'],
            ['key' => 'smtp_username', 'value' => 'noreply@courierjamaica.com', 'group' => 'email'],
            ['key' => 'smtp_password', 'value' => '', 'group' => 'email'], // To be set manually
            ['key' => 'mail_from_address', 'value' => 'noreply@courierjamaica.com', 'group' => 'email'],
            ['key' => 'mail_from_name', 'value' => 'Courier Jamaica Ltd', 'group' => 'email'],
            ['key' => 'sms_provider', 'value' => 'twilio', 'group' => 'sms'],
            ['key' => 'sms_api_key', 'value' => '', 'group' => 'sms'], // To be set manually
            ['key' => 'default_currency', 'value' => 'JMD', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'America/Jamaica', 'group' => 'general'],
            ['key' => 'courier_registration_enabled', 'value' => '1', 'group' => 'registration'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'group' => 'system'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }
    }
}
