<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'slug',
        'subdomain',
        'custom_domain',
        'logo',
        'email',
        'phone',
        'tax_identifier',
        'package_service_fee_jmd',
        'billing_model',
        'expected_monthly_packages',
        'per_package_base_fee_jmd',
        'credit_balance_jmd',
        'onboarding_documents',
        'currency',
        'timezone',
        'address',
        'city',
        'parish',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'package_service_fee_jmd' => 'decimal:2',
        'expected_monthly_packages' => 'integer',
        'per_package_base_fee_jmd' => 'decimal:2',
        'credit_balance_jmd' => 'decimal:2',
        'onboarding_documents' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function subscription()
    {
        return $this->hasOne(TenantSubscription::class);
    }

    public function payments()
    {
        return $this->hasMany(TenantPayment::class);
    }

    public function superadminBills()
    {
        return $this->hasMany(SuperadminBill::class);
    }

    public function creditTransactions()
    {
        return $this->hasMany(TenantCreditTransaction::class);
    }

    public function portalUrl(): string
    {
        if ($this->custom_domain) {
            return $this->portalScheme().'://'.$this->custom_domain;
        }

        $baseHost = $this->portalBaseHost();

        return $this->portalScheme().'://'.$this->subdomain.'.'.$baseHost;
    }

    private function portalBaseHost(): string
    {
        foreach ([
            env('APP_DOMAIN'),
            parse_url(config('app.url'), PHP_URL_HOST),
            parse_url((string) SystemSetting::where('key', 'app_url')->value('value'), PHP_URL_HOST),
            request()->getHost(),
        ] as $host) {
            $host = $this->normalizePortalHost($host);

            if ($host && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
            }
        }

        return 'localhost';
    }

    private function portalScheme(): string
    {
        foreach ([
            parse_url(config('app.url'), PHP_URL_SCHEME),
            parse_url((string) SystemSetting::where('key', 'app_url')->value('value'), PHP_URL_SCHEME),
            request()->getScheme(),
        ] as $scheme) {
            if (in_array($scheme, ['http', 'https'], true)) {
                return $scheme;
            }
        }

        return 'https';
    }

    private function normalizePortalHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        if (Str::contains($host, '://')) {
            $host = parse_url($host, PHP_URL_HOST) ?: '';
        }

        return preg_replace('/:\d+$/', '', $host) ?: '';
    }
}
