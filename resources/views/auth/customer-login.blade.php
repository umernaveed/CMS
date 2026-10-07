@extends('layouts.app')

@section('title', (($currentTenant ?? null)?->company_name ?? config('app.name')).' Customer Portal')

@section('content')
@php
    $tenant = $currentTenant ?? null;
    $settings = $platformSettings ?? collect();
    $brandName = optional($tenant)->company_name ?: config('app.name', 'Courier SaaS');
    $logoUrl = function (?string $path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    };
    $brandLogo = $logoUrl(optional($tenant)->logo);
    $brandInitials = collect(explode(' ', $brandName))->filter()->take(2)->map(fn ($word) => strtoupper(substr($word, 0, 1)))->implode('') ?: 'CP';
    $tenantLoginSettings = $tenant
        ? \App\Models\TenantSetting::where('tenant_id', $tenant->id)->whereIn('key', [
            'brand_primary_color',
            'brand_secondary_color',
            'login_headline',
            'login_intro_text',
            'login_feature_1',
            'login_feature_2',
            'login_feature_3',
            'login_feature_icon_1',
            'login_feature_icon_2',
            'login_feature_icon_3',
        ])->pluck('value', 'key')
        : collect();
    $primary = $tenantLoginSettings['brand_secondary_color'] ?? ($settings['brand_secondary_color'] ?? '#002e5d');
    $accent = $tenantLoginSettings['brand_primary_color'] ?? ($settings['brand_primary_color'] ?? '#0b1e36');
    $loginHeadline = $tenantLoginSettings['login_headline'] ?? 'Your fast and secure shopping solution';
    $loginIntro = $tenantLoginSettings['login_intro_text'] ?? 'Track your packages in real time with our advanced courier system.';
    $loginFeatures = [
        ['icon' => $tenantLoginSettings['login_feature_icon_1'] ?? 'fa-truck-fast', 'text' => $tenantLoginSettings['login_feature_1'] ?? 'Express Delivery'],
        ['icon' => $tenantLoginSettings['login_feature_icon_2'] ?? 'fa-shield', 'text' => $tenantLoginSettings['login_feature_2'] ?? 'Secure Shipping'],
        ['icon' => $tenantLoginSettings['login_feature_icon_3'] ?? 'fa-location-dot', 'text' => $tenantLoginSettings['login_feature_3'] ?? 'Real-time Tracking'],
    ];
    $signupParams = collect([
        'courier' => $tenant?->slug ?: $tenant?->subdomain,
        'ref' => request('ref') ?: request('referral_code') ?: request('api_key'),
    ])->filter()->all();
    $signupUrl = route('customer.register', $signupParams);
@endphp

<style>
    .customer-login-shell {
        align-items: center;
        background: #f1f5f9;
        display: flex;
        justify-content: center;
        min-height: 100vh;
        padding: 32px;
    }
    .customer-login-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 28px 70px rgba(15, 23, 42, .22);
        display: grid;
        grid-template-columns: 1fr 1fr;
        max-width: 900px;
        min-height: 600px;
        overflow: hidden;
        width: 100%;
    }
    .customer-login-brand {
        align-items: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 44px;
        text-align: center;
    }
    .customer-logo {
        align-items: center;
        background: {{ $accent }};
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
        color: #fff;
        display: flex;
        font-size: 38px;
        font-weight: 900;
        height: 160px;
        justify-content: center;
        margin-bottom: 30px;
        overflow: hidden;
        width: 160px;
    }
    .customer-logo img {
        background: #fff;
        height: 100%;
        object-fit: contain;
        padding: 14px;
        width: 100%;
    }
    .customer-login-brand h1 {
        color: {{ $accent }};
        font-size: 30px;
        font-weight: 900;
        line-height: 1.16;
        margin: 0 0 12px;
    }
    .customer-login-brand p {
        color: #64748b;
        font-size: 14px;
        line-height: 1.55;
        margin: 0 0 32px;
        max-width: 330px;
    }
    .customer-feature-list {
        display: grid;
        gap: 12px;
        max-width: 360px;
        width: 100%;
    }
    .customer-feature {
        align-items: center;
        background: #f8fafc;
        border-radius: 8px;
        color: #334155;
        display: flex;
        font-size: 14px;
        font-weight: 800;
        gap: 14px;
        padding: 15px 16px;
        text-align: left;
    }
    .customer-feature i {
        color: {{ $accent }};
        font-size: 18px;
        width: 22px;
    }
    .customer-login-form-panel {
        align-items: center;
        background: {{ $primary }};
        color: #fff;
        display: flex;
        justify-content: center;
        padding: 44px;
    }
    .customer-login-form {
        max-width: 360px;
        width: 100%;
    }
    .customer-login-form h2 {
        color: #fff;
        font-size: 31px;
        font-weight: 850;
        margin: 0 0 8px;
        text-align: center;
    }
    .customer-login-form .subtitle {
        color: rgba(255, 255, 255, .78);
        font-size: 14px;
        margin-bottom: 30px;
        text-align: center;
    }
    .customer-login-form .form-label {
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 8px;
    }
    .customer-login-form .form-control {
        border: 0;
        border-radius: 6px;
        min-height: 43px;
    }
    .customer-login-form .form-control:focus {
        box-shadow: 0 0 0 .22rem rgba(147, 197, 253, .38);
    }
    .customer-password-wrap {
        position: relative;
    }
    .customer-password-toggle {
        align-items: center;
        background: transparent;
        border: 0;
        color: #94a3b8;
        display: flex;
        height: 38px;
        justify-content: center;
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 38px;
    }
    .customer-login-row {
        align-items: center;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin: 18px 0 28px;
    }
    .customer-login-row label {
        align-items: center;
        display: flex;
        font-size: 12px;
        font-weight: 700;
        gap: 8px;
        margin: 0;
    }
    .customer-login-row a,
    .customer-register-link a,
    .customer-admin-link a {
        color: #fff;
        font-size: 12px;
        font-weight: 850;
    }
    .customer-submit {
        background: #020617;
        border: 0;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-weight: 850;
        min-height: 48px;
        width: 100%;
    }
    .customer-submit:hover,
    .customer-submit:focus {
        background: #111827;
        color: #fff;
    }
    .customer-register-link,
    .customer-admin-link {
        color: rgba(255, 255, 255, .82);
        font-size: 12px;
        margin-top: 24px;
        text-align: center;
    }
    .customer-admin-link {
        margin-top: 10px;
    }
    @media (max-width: 840px) {
        .customer-login-shell {
            padding: 18px;
        }
        .customer-login-card {
            grid-template-columns: 1fr;
        }
        .customer-login-brand,
        .customer-login-form-panel {
            padding: 32px 24px;
        }
        .customer-login-brand {
            min-height: auto;
        }
        .customer-logo {
            height: 118px;
            width: 118px;
        }
    }
    @media (max-width: 520px) {
        .customer-login-shell {
            align-items: stretch;
            padding: 0;
        }
        .customer-login-card {
            border-radius: 0;
            min-height: 100vh;
        }
        .customer-login-row {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="customer-login-shell">
    <div class="customer-login-card">
        <section class="customer-login-brand">
            <div class="customer-logo">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }} logo">
                @else
                    {{ $brandInitials }}
                @endif
            </div>

            <h1>{{ $loginHeadline }}</h1>
            <p>{{ $loginIntro }}</p>

            <div class="customer-feature-list">
                @foreach($loginFeatures as $feature)
                    @if(filled($feature['text']))
                        <div class="customer-feature"><i class="fas {{ $feature['icon'] }}"></i><span>{{ $feature['text'] }}</span></div>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="customer-login-form-panel">
            <div class="customer-login-form">
                <h2>Welcome to {{ $brandName }}</h2>
                <div class="subtitle">Sign in to your customer account</div>

                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if(isset($errors) && $errors->any())
                    <div class="alert alert-danger">
                        <div class="fw-bold mb-1">Sign in failed</div>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="customer_email" class="form-label">Email or Mailbox</label>
                        <input type="text" class="form-control {{ isset($errors) && $errors->has('email') ? 'is-invalid' : '' }}" id="customer_email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        @if(isset($errors) && $errors->has('email'))<div class="invalid-feedback">{{ $errors->first('email') }}</div>@endif
                    </div>

                    <div>
                        <label for="customer_password" class="form-label">Password</label>
                        <div class="customer-password-wrap">
                            <input type="password" class="form-control pe-5 {{ isset($errors) && $errors->has('password') ? 'is-invalid' : '' }}" id="customer_password" name="password" required>
                            <button type="button" class="customer-password-toggle" aria-label="Show password" data-customer-password-toggle><i class="fas fa-eye"></i></button>
                        </div>
                        @if(isset($errors) && $errors->has('password'))<div class="invalid-feedback d-block">{{ $errors->first('password') }}</div>@endif
                    </div>

                    <div class="customer-login-row">
                        <label for="customer_remember">
                            <input id="customer_remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span>Remember me</span>
                        </label>
                        <a href="{{ route('password.request') }}">Forgot your password?</a>
                    </div>

                    <button type="submit" class="customer-submit">Sign in</button>
                </form>

                <div class="customer-register-link">Don't have an account? <a href="{{ $signupUrl }}">Sign up</a></div>
                <div class="customer-admin-link">Courier staff? <a href="{{ route('login') }}">Use login</a></div>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('[data-customer-password-toggle]');
    const input = document.getElementById('customer_password');
    if (!toggle || !input) return;

    toggle.addEventListener('click', function () {
        input.type = input.type === 'password' ? 'text' : 'password';
        toggle.innerHTML = input.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
});
</script>
@endsection
