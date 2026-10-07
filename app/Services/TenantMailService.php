<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Mail\TenantTemplateMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TenantMailService
{
    public function send(?int $tenantId, string $to, Mailable $mailable): void
    {
        $lastException = null;
        $candidates = $this->settingsCandidates($tenantId);
        $attemptedProviders = [];

        Log::info('Resolving mail providers for email send', [
            'tenant_id' => $tenantId,
            'to' => $to,
            'providers' => collect($candidates)->map(fn ($settings) => [
                'source' => $settings['source'] ?? 'unknown',
                'mailer' => $settings['mailer'] ?? 'smtp',
                'host' => $settings['smtp_host'] ?? null,
                'port' => $settings['smtp_port'] ?? null,
                'encryption' => $settings['smtp_encryption'] ?? null,
                'username_present' => filled($settings['smtp_username'] ?? null),
                'password_present' => filled($settings['smtp_password'] ?? null),
                'from' => $settings['mail_from_address'] ?? null,
            ])->values()->all(),
        ]);

        foreach ($candidates as $settings) {
            $attemptedProviders[] = $this->providerLabel($settings);

            try {
                $mailerName = $this->configureResolvedMailer($settings);
                $message = clone $mailable;
                $this->applyFromAddress($message, $settings);

                Log::info('Trying mail provider', [
                    'provider' => $settings['source'] ?? 'unknown',
                    'mailer_name' => $mailerName,
                    'tenant_id' => $tenantId,
                    'to' => $to,
                    'host' => $settings['smtp_host'] ?? null,
                    'port' => $settings['smtp_port'] ?? null,
                    'encryption' => $settings['smtp_encryption'] ?? null,
                    'from' => $settings['mail_from_address'] ?? null,
                ]);

                Mail::mailer($mailerName)->to($to)->send($message);

                Log::info('Mail provider sent email successfully', [
                    'provider' => $settings['source'] ?? 'unknown',
                    'mailer_name' => $mailerName,
                    'tenant_id' => $tenantId,
                    'to' => $to,
                ]);
                return;
            } catch (Throwable $exception) {
                $lastException = $exception;
                Log::warning('Email send failed; trying next mail provider if available.', [
                    'provider' => $settings['source'] ?? 'unknown',
                    'tenant_id' => $tenantId,
                    'to' => $to,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($candidates === []) {
            Log::error('No usable mail provider was available for email send', [
                'tenant_id' => $tenantId,
                'to' => $to,
            ]);
        }

        throw new RuntimeException(
            'Email could not be sent'
                .($attemptedProviders !== [] ? ' after trying '.implode(', ', array_unique($attemptedProviders)) : ' because no usable provider was configured')
                .'.'
                .($lastException ? ' Last error: '.$lastException->getMessage() : ''),
            0,
            $lastException
        );
    }

    public function applySettings(?int $tenantId = null): void
    {
        $settings = $this->settingsCandidates($tenantId)[0] ?? $this->settingsFromBaseConfig();

        $mailerName = $this->configureResolvedMailer($settings);

        config([
            'mail.default' => $mailerName,
            'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
        ]);
    }

    public function testSmtpSettings(?int $tenantId, array $settings, string $to): void
    {
        $tenantSettings = $this->cleanSettings($settings);
        $systemSettings = $this->settingsFromSystem();
        $configSettings = $this->settingsFromBaseConfig();

        if ($this->usableMailSettings($tenantSettings)) {
            $settings = $this->resolvedSettings(
                array_merge($tenantSettings, ['source' => 'tenant_test']),
                'tenant_test',
                $tenantSettings,
                [],
                $configSettings
            );
        } elseif ($this->usableMailSettings($systemSettings)) {
            $settings = $this->resolvedSettings(
                $systemSettings,
                'platform_test',
                $tenantId ? $this->tenantFallbackIdentity($tenantId, $tenantSettings) : [],
                $systemSettings,
                $configSettings
            );
        } else {
            $settings = $this->resolvedSettings(
                $configSettings,
                'environment_test',
                $tenantId ? $this->tenantFallbackIdentity($tenantId, $tenantSettings) : [],
                [],
                $configSettings
            );
        }

        if (! $this->usableMailSettings($settings)) {
            throw new RuntimeException('No courier, System Admin, or environment SMTP host is configured before sending a test email.');
        }

        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $mailerName = $this->configureResolvedMailer($settings);
        $subject = 'SMTP test from '.($settings['mail_from_name'] ?? config('app.name'));
        $message = new TenantTemplateMail($subject, [
            'tenant' => $tenant,
            'subject' => $subject,
            'headline' => 'SMTP test successful',
            'bodyHtml' => '<p>This message was sent using '.(($settings['source'] ?? '') === 'tenant_test' ? 'the courier SMTP settings currently entered in the portal' : 'the System Admin/default SMTP fallback').'.</p>',
            'details' => [
                'SMTP Host' => $settings['smtp_host'] ?? '-',
                'SMTP Port' => $settings['smtp_port'] ?? '-',
                'Encryption' => $settings['smtp_encryption'] ?? 'none',
                'From Email' => $settings['mail_from_address'] ?? '-',
            ],
            'buttonLabel' => '',
            'buttonUrl' => '',
            'footerHtml' => '',
            'footerText' => '',
            'attachmentUrl' => '',
        ]);
        $this->applyFromAddress($message, $settings);

        Log::info('Sending courier SMTP test email', [
            'tenant_id' => $tenantId,
            'to' => $to,
            'host' => $settings['smtp_host'] ?? null,
            'port' => $settings['smtp_port'] ?? null,
            'encryption' => $settings['smtp_encryption'] ?? null,
            'from' => $settings['mail_from_address'] ?? null,
        ]);

        Mail::mailer($mailerName)->to($to)->send($message);
    }

    private function configureResolvedMailer(array $settings): string
    {
        $mailer = $settings['mailer'] ?? 'smtp';
        $mailerName = 'resolved_'.$this->normaliseMailerName($settings['source'] ?? $mailer).'_'.$this->settingsFingerprint($settings);

        if ($mailer === 'smtp') {
            config([
                'mail.mailers.'.$mailerName => [
                    'transport' => 'smtp',
                    'host' => $settings['smtp_host'] ?? null,
                    'port' => $settings['smtp_port'] ?? 587,
                    'encryption' => $settings['smtp_encryption'] ?? null,
                    'username' => $settings['smtp_username'] ?? null,
                    'password' => $settings['smtp_password'] ?? null,
                    'timeout' => config('mail.mailers.smtp.timeout'),
                    'local_domain' => config('mail.mailers.smtp.local_domain'),
                ],
            ]);
        } else {
            config([
                'mail.mailers.'.$mailerName => config('mail.mailers.'.$mailer, ['transport' => $mailer]),
            ]);
        }

        app('mail.manager')->purge($mailerName);

        return $mailerName;
    }

    private function applyFromAddress(Mailable $mailable, array $settings): void
    {
        if (filled($settings['mail_from_address'] ?? null)) {
            $mailable->from($settings['mail_from_address'], $settings['mail_from_name'] ?? null);
        }
    }

    public function settings(?int $tenantId = null): array
    {
        return $this->settingsCandidates($tenantId)[0] ?? $this->settingsFromBaseConfig();
    }

    private function settingsCandidates(?int $tenantId = null): array
    {
        $tenantSettings = $tenantId ? $this->settingsFromTenant($tenantId) : [];
        $tenantFallbackIdentity = $tenantId ? $this->tenantFallbackIdentity($tenantId, $tenantSettings) : [];
        $systemSettings = $this->settingsFromSystem();
        $configSettings = $this->settingsFromBaseConfig();

        $candidates = [];

        if ($tenantId !== null) {
            if ($this->tenantSmtpIsActive($tenantSettings)) {
                $tenantCandidate = $this->resolvedSettings(
                    $tenantSettings,
                    'tenant',
                    $tenantSettings,
                    [],
                    $configSettings
                );

                Log::info('Courier SMTP configured; superadmin mail fallback remains available for tenant email send.', [
                    'tenant_id' => $tenantId,
                    'host' => $tenantCandidate['smtp_host'] ?? null,
                    'port' => $tenantCandidate['smtp_port'] ?? null,
                    'encryption' => $tenantCandidate['smtp_encryption'] ?? null,
                    'username_present' => filled($tenantCandidate['smtp_username'] ?? null),
                    'from' => $tenantCandidate['mail_from_address'] ?? null,
                ]);

                $candidates[] = $tenantCandidate;
            } elseif ($this->usableMailSettings($tenantSettings)) {
                Log::info('Courier SMTP is configured but not active; using superadmin mail fallback.', [
                    'tenant_id' => $tenantId,
                    'smtp_status' => $tenantSettings['smtp_status'] ?? 'untested',
                    'host' => $tenantSettings['smtp_host'] ?? null,
                ]);
            }
        }

        if ($this->usableMailSettings($systemSettings)) {
            $candidates[] = $this->resolvedSettings(
                $systemSettings,
                'platform',
                $tenantFallbackIdentity,
                $systemSettings,
                $configSettings
            );
        }

        if ($this->usableMailSettings($configSettings)) {
            $candidates[] = $this->resolvedSettings(
                $configSettings,
                'environment',
                $tenantFallbackIdentity,
                [],
                $configSettings
            );
        }

        return $this->uniqueCandidates($candidates);
    }

    private function resolvedSettings(array $smtpSettings, string $source, array $tenantSettings, array $systemSettings, array $configSettings): array
    {
        return [
            'source' => $source,
            'mailer' => $smtpSettings['mailer'] ?? 'smtp',
            'smtp_host' => $smtpSettings['smtp_host'] ?? null,
            'smtp_port' => (int) (($smtpSettings['smtp_port'] ?? null) ?: 587),
            'smtp_encryption' => $this->encryption($smtpSettings['smtp_encryption'] ?? null),
            'smtp_username' => $smtpSettings['smtp_username'] ?? null,
            'smtp_password' => $smtpSettings['smtp_password'] ?? null,
            'mail_from_address' => $tenantSettings['mail_from_address']
                ?? $systemSettings['mail_from_address']
                ?? $systemSettings['company_email']
                ?? $smtpSettings['mail_from_address']
                ?? $smtpSettings['smtp_username']
                ?? $configSettings['mail_from_address']
                ?? config('mail.from.address'),
            'mail_from_name' => $tenantSettings['mail_from_name']
                ?? $systemSettings['mail_from_name']
                ?? $systemSettings['company_name']
                ?? $smtpSettings['mail_from_name']
                ?? $configSettings['mail_from_name']
                ?? config('mail.from.name'),
        ];
    }

    private function settingsFromTenant(int $tenantId): array
    {
        return $this->cleanSettings([
            'smtp_host' => TenantSetting::getValue($tenantId, 'smtp_host'),
            'smtp_port' => TenantSetting::getValue($tenantId, 'smtp_port'),
            'smtp_encryption' => TenantSetting::getValue($tenantId, 'smtp_encryption'),
            'smtp_username' => TenantSetting::getValue($tenantId, 'smtp_username'),
            'smtp_password' => $this->storedSecret(TenantSetting::getValue($tenantId, 'smtp_password')),
            'mail_from_address' => TenantSetting::getValue($tenantId, 'mail_from_address'),
            'mail_from_name' => TenantSetting::getValue($tenantId, 'mail_from_name'),
            'smtp_status' => TenantSetting::getValue($tenantId, 'smtp_status'),
        ]);
    }

    private function tenantFallbackIdentity(int $tenantId, array $tenantSettings): array
    {
        $tenantName = $tenantSettings['mail_from_name']
            ?? optional(Tenant::find($tenantId))->company_name;

        return $this->cleanSettings([
            'mail_from_name' => $tenantName,
        ]);
    }

    private function settingsFromSystem(): array
    {
        $settings = SystemSetting::whereIn('key', [
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'mail_from_address',
            'mail_from_name',
            'company_email',
            'company_name',
        ])->pluck('value', 'key')->all();

        return $this->cleanSettings($settings);
    }

    private function settingsFromBaseConfig(): array
    {
        return $this->cleanSettings([
            'mailer' => config('mail.default', 'smtp'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port', 587),
            'smtp_encryption' => config('mail.mailers.smtp.encryption'),
            'smtp_username' => config('mail.mailers.smtp.username'),
            'smtp_password' => config('mail.mailers.smtp.password'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ]);
    }

    private function cleanSettings(array $settings): array
    {
        return collect($settings)
            ->map(fn ($value) => filled($value) ? trim((string) $value) : null)
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function usableMailSettings(array $settings): bool
    {
        $mailer = $settings['mailer'] ?? 'smtp';

        if ($mailer !== 'smtp') {
            return filled($mailer);
        }

        return filled($settings['smtp_host'] ?? null);
    }

    private function tenantSmtpIsActive(array $settings): bool
    {
        return ($settings['smtp_status'] ?? null) === 'active'
            && $this->usableMailSettings($settings);
    }

    private function uniqueCandidates(array $candidates): array
    {
        $seen = [];

        return array_values(array_filter($candidates, function (array $settings) use (&$seen) {
            $key = implode('|', [
                $settings['mailer'] ?? 'smtp',
                $settings['smtp_host'] ?? '',
                $settings['smtp_port'] ?? '',
                $settings['smtp_username'] ?? '',
                $settings['mail_from_address'] ?? '',
            ]);

            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;
            return true;
        }));
    }

    private function providerLabel(array $settings): string
    {
        return match ($settings['source'] ?? null) {
            'tenant' => 'courier SMTP',
            'platform' => 'superadmin SMTP',
            'environment' => 'environment mail config',
            default => ($settings['source'] ?? 'mail provider'),
        };
    }

    private function normaliseMailerName(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($name)) ?: 'mail';

        return trim($name, '_') ?: 'mail';
    }

    private function settingsFingerprint(array $settings): string
    {
        return substr(sha1(json_encode([
            'mailer' => $settings['mailer'] ?? 'smtp',
            'host' => $settings['smtp_host'] ?? null,
            'port' => $settings['smtp_port'] ?? null,
            'encryption' => $settings['smtp_encryption'] ?? null,
            'username' => $settings['smtp_username'] ?? null,
            'from' => $settings['mail_from_address'] ?? null,
        ])), 0, 12);
    }

    private function encryption($value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['tls', 'ssl'], true) ? $value : null;
    }

    private function storedSecret($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, 'encrypted:')) {
            return $value;
        }

        try {
            return decrypt(substr($value, strlen('encrypted:')));
        } catch (Throwable $exception) {
            Log::warning('Stored SMTP password could not be decrypted.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
