<?php

namespace Tests\Unit;

use App\Services\EmailTemplateService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EmailTemplateServiceRenderTest extends TestCase
{
    public function test_it_renders_plain_and_html_entity_encoded_tokens(): void
    {
        $service = new EmailTemplateService();
        $method = (new ReflectionClass($service))->getMethod('render');
        $method->setAccessible(true);

        $tokens = [
            'courier_name' => 'JC Grants Logistics & Travel',
            'mailbox' => 'OL-GRANTS10001',
        ];

        $this->assertSame(
            'Welcome to JC Grants Logistics & Travel. Mailbox: OL-GRANTS10001',
            $method->invoke($service, 'Welcome to {{ courier_name }}. Mailbox: {{ mailbox }}', $tokens)
        );

        $this->assertSame(
            'Welcome to JC Grants Logistics & Travel. Mailbox: OL-GRANTS10001',
            $method->invoke($service, 'Welcome to &#123;&#123; courier_name &#125;&#125;. Mailbox: &#123;&#123; MAILBOX &#125;&#125;', $tokens)
        );
    }

    public function test_it_removes_package_cost_lines_from_email_templates(): void
    {
        $service = new EmailTemplateService();
        $reflection = new ReflectionClass($service);
        $plain = $reflection->getMethod('removePackageCostLines');
        $plain->setAccessible(true);
        $html = $reflection->getMethod('removePackageCostHtml');
        $html->setAccessible(true);

        $this->assertSame(
            "Tracking: OL-123\nStatus: Ready",
            $plain->invoke($service, "Tracking: OL-123\nCost: JMD 750.00\nStatus: Ready\nTotal Due: JMD 750.00")
        );

        $this->assertSame(
            '<p>Tracking: OL-123</p><p>Status: Ready</p>',
            $html->invoke($service, '<p>Tracking: OL-123</p><p>Cost: JMD 750.00</p><p>Status: Ready</p><div>Total Due: JMD 750.00</div>')
        );
    }
}
