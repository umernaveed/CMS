<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\TenantMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class TenantMailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_smtp_falls_back_to_platform_mail_with_platform_from_email_and_courier_from_name()
    {
        $tenant = Tenant::create([
            'company_name' => 'Courier SMTP Test',
            'slug' => 'courier-smtp-test',
            'subdomain' => 'couriersmtptest',
            'email' => 'admin@courier.test',
            'status' => 'active',
        ]);

        TenantSetting::putValue($tenant->id, 'smtp_host', 'smtp.gmail.com');
        TenantSetting::putValue($tenant->id, 'smtp_port', '587');
        TenantSetting::putValue($tenant->id, 'smtp_encryption', 'tls');
        TenantSetting::putValue($tenant->id, 'smtp_username', 'courier@gmail.com');
        TenantSetting::putValue($tenant->id, 'smtp_password', 'app-password');
        TenantSetting::putValue($tenant->id, 'mail_from_address', 'courier@gmail.com');
        TenantSetting::putValue($tenant->id, 'mail_from_name', 'Courier Gmail');

        SystemSetting::create(['key' => 'smtp_host', 'value' => 'server-mail.example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_port', 'value' => '587', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_username', 'value' => 'platform@example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_password', 'value' => 'platform-password', 'group' => 'email']);
        SystemSetting::create(['key' => 'mail_from_address', 'value' => 'platform@example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'mail_from_name', 'value' => 'Platform Mail', 'group' => 'email']);

        $method = new ReflectionMethod(TenantMailService::class, 'settingsCandidates');
        $method->setAccessible(true);

        $candidates = $method->invoke(app(TenantMailService::class), $tenant->id);

        $this->assertGreaterThanOrEqual(2, count($candidates));
        $this->assertSame('tenant', $candidates[0]['source']);
        $this->assertSame('smtp.gmail.com', $candidates[0]['smtp_host']);
        $this->assertSame('courier@gmail.com', $candidates[0]['mail_from_address']);
        $this->assertSame('platform', $candidates[1]['source']);
        $this->assertSame('server-mail.example.com', $candidates[1]['smtp_host']);
        $this->assertSame('platform@example.com', $candidates[1]['mail_from_address']);
        $this->assertSame('Courier Gmail', $candidates[1]['mail_from_name']);
    }

    public function test_platform_fallback_uses_system_from_email_and_courier_from_name_without_tenant_smtp()
    {
        $tenant = Tenant::create([
            'company_name' => 'Courier From Test',
            'slug' => 'courier-from-test',
            'subdomain' => 'courierfromtest',
            'email' => 'admin@courier.test',
            'status' => 'active',
        ]);

        TenantSetting::putValue($tenant->id, 'mail_from_address', 'courier-domain@example-courier.com');
        TenantSetting::putValue($tenant->id, 'mail_from_name', 'Courier Domain');

        SystemSetting::create(['key' => 'smtp_host', 'value' => 'server-mail.example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_port', 'value' => '587', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_username', 'value' => 'platform@example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'mail_from_address', 'value' => 'platform@example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'mail_from_name', 'value' => 'Platform Mail', 'group' => 'email']);

        $method = new ReflectionMethod(TenantMailService::class, 'settingsCandidates');
        $method->setAccessible(true);

        $candidates = $method->invoke(app(TenantMailService::class), $tenant->id);

        $this->assertNotEmpty($candidates);
        $this->assertSame('platform', $candidates[0]['source']);
        $this->assertSame('platform@example.com', $candidates[0]['mail_from_address']);
        $this->assertSame('Courier Domain', $candidates[0]['mail_from_name']);
    }

    public function test_platform_fallback_uses_tenant_company_name_when_courier_from_name_is_not_configured()
    {
        $tenant = Tenant::create([
            'company_name' => 'Company Name Courier',
            'slug' => 'company-name-courier',
            'subdomain' => 'companynamecourier',
            'email' => 'admin@courier.test',
            'status' => 'active',
        ]);

        SystemSetting::create(['key' => 'smtp_host', 'value' => 'server-mail.example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_port', 'value' => '587', 'group' => 'email']);
        SystemSetting::create(['key' => 'smtp_username', 'value' => 'platform@example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'mail_from_address', 'value' => 'platform@example.com', 'group' => 'email']);
        SystemSetting::create(['key' => 'mail_from_name', 'value' => 'Platform Mail', 'group' => 'email']);

        $method = new ReflectionMethod(TenantMailService::class, 'settingsCandidates');
        $method->setAccessible(true);

        $candidates = $method->invoke(app(TenantMailService::class), $tenant->id);

        $this->assertNotEmpty($candidates);
        $this->assertSame('platform', $candidates[0]['source']);
        $this->assertSame('platform@example.com', $candidates[0]['mail_from_address']);
        $this->assertSame('Company Name Courier', $candidates[0]['mail_from_name']);
    }
}
