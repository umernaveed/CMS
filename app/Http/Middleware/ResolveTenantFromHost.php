<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ResolveTenantFromHost
{
    public function handle(Request $request, Closure $next)
    {
        $host = $this->normalizeHost($request->getHost());
        $tenant = $this->tenantFromHost($host);
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $centralDomains = $this->centralDomains();
        $isCentralHost = $isLocalHost
            || in_array($host, $centralDomains, true)
            || (Str::startsWith($host, 'www.') && in_array(substr($host, 4), $centralDomains, true));

        app()->instance('currentTenant', $tenant);
        app()->instance('currentHostIsCentral', $isCentralHost);
        app()->instance('currentHostIsLocal', $isLocalHost);
        View::share('currentTenant', $tenant);

        return $next($request);
    }

    private function tenantFromHost(string $host): ?Tenant
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host));
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        $central = $this->centralDomains();
        if (in_array($host, $central, true)) {
            return null;
        }

        $tenant = Tenant::where('custom_domain', $host)->whereIn('status', ['active', 'trial'])->first();
        if ($tenant) {
            return $tenant;
        }

        foreach ($central as $domain) {
            if (str_ends_with($host, '.'.$domain)) {
                $subdomain = substr($host, 0, -1 * (strlen($domain) + 1));
                if ($subdomain && ! str_contains($subdomain, '.')) {
                    return Tenant::where('subdomain', $subdomain)->whereIn('status', ['active', 'trial'])->first();
                }
            }
        }

        $parts = explode('.', $host);
        if (count($parts) === 2 && ! in_array($parts[0], ['www', 'app'], true)) {
            return Tenant::where('subdomain', $parts[0])->whereIn('status', ['active', 'trial'])->first();
        }

        return null;
    }

    private function centralDomains(): array
    {
        $domains = [];
        foreach (array_filter(array_map('trim', explode(',', (string) env('CENTRAL_DOMAINS', '')))) as $domain) {
            $host = $this->normalizeHost($domain);
            if ($host) {
                $domains[] = $host;
            }
        }

        foreach ([
            env('APP_DOMAIN'),
            parse_url(config('app.url'), PHP_URL_HOST),
            parse_url((string) SystemSetting::where('key', 'app_url')->value('value'), PHP_URL_HOST),
        ] as $host) {
            $host = $this->normalizeHost($host);
            if ($host && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                $domains[] = $host;
            }
        }

        return array_values(array_unique(array_filter($domains)));
    }

    private function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        if (Str::contains($host, '://')) {
            $host = parse_url($host, PHP_URL_HOST) ?: '';
        }

        return preg_replace('/:\d+$/', '', $host) ?: '';
    }
}
