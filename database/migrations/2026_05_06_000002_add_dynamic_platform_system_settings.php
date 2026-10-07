<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['key' => 'company_logo', 'value' => null, 'group' => 'general'],
            ['key' => 'favicon', 'value' => null, 'group' => 'general'],
            ['key' => 'platform_tagline', 'value' => 'Run courier operations with confidence', 'group' => 'general'],
            ['key' => 'platform_subtitle', 'value' => 'SaaS Operations', 'group' => 'general'],
            ['key' => 'login_intro_text', 'value' => 'Manage tenants, packages, billing, customers, manifests, and delivery workflows from a single platform.', 'group' => 'general'],
            ['key' => 'login_background_url', 'value' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1800&q=80', 'group' => 'general'],
            ['key' => 'login_panel_image_url', 'value' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=1200&q=80', 'group' => 'general'],
            ['key' => 'brand_primary_color', 'value' => '#135fd2', 'group' => 'general'],
            ['key' => 'brand_secondary_color', 'value' => '#073b70', 'group' => 'general'],
            ['key' => 'support_email', 'value' => null, 'group' => 'general'],
            ['key' => 'email_footer', 'value' => null, 'group' => 'email'],
        ] as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group']]
            );
        }
    }

    public function down(): void
    {
        SystemSetting::whereIn('key', [
            'company_logo',
            'favicon',
            'platform_tagline',
            'platform_subtitle',
            'login_intro_text',
            'login_background_url',
            'login_panel_image_url',
            'brand_primary_color',
            'brand_secondary_color',
            'support_email',
            'email_footer',
        ])->delete();
    }
};
