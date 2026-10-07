<?php

namespace Tests\Unit;

use App\Support\SessionCookieDomain;
use PHPUnit\Framework\TestCase;

class SessionCookieDomainTest extends TestCase
{
    /**
     * @dataProvider productionDomainProvider
     */
    public function test_it_normalizes_production_cookie_domains(
        ?string $sessionDomain,
        ?string $appDomain,
        ?string $appUrl,
        ?string $expected
    ): void {
        $this->assertSame(
            $expected,
            SessionCookieDomain::resolve($sessionDomain, $appDomain, $appUrl)
        );
    }

    public static function productionDomainProvider(): array
    {
        return [
            'explicit root domain' => ['.example.com', null, null, '.example.com'],
            'explicit URL is tolerated' => ['https://www.example.com', null, null, '.example.com'],
            'app domain with port' => [null, 'www.example.com:443', null, '.example.com'],
            'app URL fallback' => [null, null, 'https://www.example.com/login', '.example.com'],
            'localhost has a host-only cookie' => [null, 'localhost', 'http://localhost', null],
            'IPv4 has a host-only cookie' => [null, '127.0.0.1', 'http://127.0.0.1', null],
        ];
    }
}
