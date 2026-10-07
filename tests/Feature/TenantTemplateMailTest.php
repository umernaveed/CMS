<?php

namespace Tests\Feature;

use App\Mail\TenantTemplateMail;
use Tests\TestCase;

class TenantTemplateMailTest extends TestCase
{
    public function test_tenant_template_mail_renders_without_explicit_tenant_payload()
    {
        $html = (new TenantTemplateMail('SMTP test', [
            'bodyHtml' => '<p>Testing SMTP settings.</p>',
        ]))->render();

        $this->assertStringContainsString('SMTP test', $html);
        $this->assertStringContainsString('Testing SMTP settings.', $html);
    }
}
