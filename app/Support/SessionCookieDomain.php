<?php

namespace App\Support;

class SessionCookieDomain
{
    public static function resolve(?string $sessionDomain, ?string $appDomain, ?string $appUrl): ?string
    {
        $configuredDomain = trim((string) $sessionDomain);
        $host = $configuredDomain !== ''
            ? $configuredDomain
            : (trim((string) $appDomain) ?: (string) $appUrl);

        $host = self::normalizeHost($host);

        if (! $host || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        return '.'.preg_replace('/^www\./', '', $host);
    }

    private static function normalizeHost(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = ltrim($value, '.');
        $parsedHost = parse_url(
            str_contains($value, '://') ? $value : 'https://'.$value,
            PHP_URL_HOST
        );

        return strtolower(rtrim((string) $parsedHost, '.'));
    }
}
