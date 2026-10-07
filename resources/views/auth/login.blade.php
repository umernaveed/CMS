@extends('layouts.app')

@section('title', 'Login')

@section('content')
@php
    $tenantContext = $currentTenant ?? null;
    $settings = $platformSettings ?? collect();

    $settingValue = function ($key, $default = null) use ($settings) {
        if (is_array($settings)) {
            return $settings[$key] ?? $default;
        }
        return $settings[$key] ?? $default;
    };

    $resolvedPlatformName = $platformName ?? ($settingValue('app_name') ?? config('app.name', 'Courier SaaS'));

    $logoUrl = function (?string $path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    };

    $brandName = $tenantContext ? $tenantContext->company_name : $resolvedPlatformName;
    $brandLogo = $tenantContext
        ? $logoUrl($tenantContext->logo)
        : ($platformLogo ?? $logoUrl($settingValue('app_logo') ?? $settingValue('company_logo') ?? null));
    $brandInitials = collect(explode(' ', $brandName))->filter()->take(2)->map(fn($word) => strtoupper(substr($word, 0, 1)))->implode('') ?: 'CS';

    $tenantLoginSettings = $tenantContext
        ? \App\Models\TenantSetting::where('tenant_id', $tenantContext->id)->whereIn('key', [
            'login_kicker',
            'login_headline',
            'login_intro_text',
            'login_feature_1',
            'login_feature_2',
            'login_feature_3',
            'login_feature_icon_1',
            'login_feature_icon_2',
            'login_feature_icon_3',
            'login_panel_image_url',
            'login_background_url',
            'brand_primary_color',
            'brand_secondary_color',
            'primary_color',
            'secondary_color',
            'accent_color',
        ])->pluck('value', 'key')
        : collect();

    $tenantSettingValue = function ($key, $default = null) use ($tenantLoginSettings) {
        return $tenantLoginSettings[$key] ?? $default;
    };

    $loginBackground = $logoUrl(($tenantContext ? $tenantSettingValue('login_background_url') : null) ?: ($settingValue('login_background_url') ?? null))
        ?: 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1800&q=80';

    $loginPanelImage = $logoUrl(($tenantContext ? ($tenantSettingValue('login_panel_image_url') ?? null) : null)
        ?: ($settingValue('login_panel_image_url') ?? null));

    $primaryColor = ($tenantContext ? ($tenantSettingValue('brand_primary_color') ?? $tenantSettingValue('primary_color')) : null)
        ?: ($settingValue('primary_color') ?? $settingValue('brand_primary_color') ?? '#002e5d');
    $secondaryColor = ($tenantContext ? ($tenantSettingValue('brand_secondary_color') ?? $tenantSettingValue('secondary_color')) : null)
        ?: ($settingValue('secondary_color') ?? $settingValue('brand_secondary_color') ?? '#0B1E36');
    $accentColor = ($tenantContext ? $tenantSettingValue('accent_color') : null)
        ?: ($settingValue('accent_color') ?? $settingValue('brand_accent_color') ?? '#FF7A00');

    $platformTagline = $settingValue('platform_tagline') ?? 'Your fast and secure shipping solution';
    $platformIntro = $settingValue('login_intro_text') ?? 'Track your packages in real-time with our advanced courier system';
    $courierRegistrationValue = strtolower(trim((string) ($settingValue('courier_registration_enabled') ?? '1')));
    $courierRegistrationEnabled = ! in_array($courierRegistrationValue, ['0', 'false', 'off', 'no'], true);

    $loginKicker = $tenantContext ? ($tenantSettingValue('login_kicker') ?? 'Secure customer access') : 'Customer portal';
    $loginHeadline = $tenantContext ? ($tenantSettingValue('login_headline') ?? $platformTagline) : $platformTagline;
    $loginIntro = $tenantContext ? ($tenantSettingValue('login_intro_text') ?? $platformIntro) : $platformIntro;

    $loginFeatures = [
        ['icon' => $tenantContext ? ($tenantSettingValue('login_feature_icon_1') ?? 'fa-truck-fast') : 'fa-truck-fast', 'text' => $tenantContext ? ($tenantSettingValue('login_feature_1') ?? 'Express Delivery') : 'Express Delivery'],
        ['icon' => $tenantContext ? ($tenantSettingValue('login_feature_icon_2') ?? 'fa-shield') : 'fa-shield', 'text' => $tenantContext ? ($tenantSettingValue('login_feature_2') ?? 'Secure Shipping') : 'Secure Shipping'],
        ['icon' => $tenantContext ? ($tenantSettingValue('login_feature_icon_3') ?? 'fa-location-dot') : 'fa-location-dot', 'text' => $tenantContext ? ($tenantSettingValue('login_feature_3') ?? 'Real-time Tracking') : 'Real-time Tracking'],
    ];
@endphp

<style>
    :root {
        --login-primary: {{ $primaryColor }};
        --login-secondary: {{ $secondaryColor }};
        --login-accent: {{ $accentColor }};
    }

    .customer-login-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        background:
            linear-gradient(135deg, rgba(241, 245, 249, .94), rgba(241, 245, 249, .88)),
            url("{{ $loginBackground }}") center/cover no-repeat;
    }

    .customer-login-card {
        width: min(900px, 100%);
        min-height: 600px;
        display: flex;
        overflow: hidden;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 26px 70px rgba(15, 23, 42, .22);
        animation: customerLoginFade .3s ease-out forwards;
    }

    @keyframes customerLoginFade {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .customer-login-left,
    .customer-login-right {
        width: 50%;
    }

    .customer-login-left {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 44px 48px;
        background: #fff;
        text-align: center;
        overflow: hidden;
    }

    @if($loginPanelImage)
    .customer-login-left::before {
        content: "";
        position: absolute;
        inset: 0;
        background: url("{{ $loginPanelImage }}") center/cover no-repeat;
        opacity: .18;
        pointer-events: none;
    }
    @endif

    .customer-login-left > * {
        position: relative;
        z-index: 1;
    }

    .customer-login-logo {
        width: 160px;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30px;
        overflow: hidden;
        border-radius: 7px;
        background: var(--login-secondary);
        color: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .10);
    }

    .customer-login-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #fff;
        padding: 14px;
    }

    .customer-login-logo-mark {
        font-size: 46px;
        font-weight: 950;
        letter-spacing: -1px;
    }

    .customer-login-title {
        max-width: 360px;
        margin: 0 auto 12px;
        color: var(--login-secondary);
        font-size: 30px;
        line-height: 1.16;
        font-weight: 900;
        letter-spacing: -.5px;
    }

    .customer-login-intro {
        max-width: 330px;
        margin: 0 auto 38px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.65;
    }

    .customer-login-features {
        width: 100%;
        max-width: 350px;
        display: grid;
        gap: 12px;
    }

    .customer-login-feature {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 15px 16px;
        border-radius: 10px;
        background: #f8fafc;
        color: #334155;
        text-align: left;
        font-size: 14px;
        font-weight: 800;
    }

    .customer-login-feature i {
        width: 22px;
        text-align: center;
        color: var(--login-secondary);
        font-size: 18px;
    }

    .customer-login-right {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 44px 48px;
        background: var(--login-primary);
        color: #fff;
    }

    .customer-login-form-inner {
        width: min(350px, 100%);
    }

    .customer-login-welcome {
        color: #fff;
        font-size: 30px;
        line-height: 1.15;
        font-weight: 850;
        margin-bottom: 8px;
        text-align: center;
    }

    .customer-login-subtitle {
        margin-bottom: 30px;
        color: rgba(219, 234, 254, .95);
        font-size: 14px;
        text-align: center;
    }

    .customer-login-right .form-label {
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .customer-login-right .form-control {
        min-height: 44px;
        border: 0;
        border-radius: 6px;
        background: #fff;
        color: #0f172a;
        font-size: 15px;
        box-shadow: none;
    }

    .customer-login-right .form-control:focus {
        box-shadow: 0 0 0 .22rem rgba(255,255,255,.28);
    }

    .customer-password-wrap {
        position: relative;
    }

    .customer-password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border: 0;
        background: transparent;
        color: #94a3b8;
        font-size: 16px;
    }

    .customer-login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 18px 0 24px;
    }

    .customer-login-check {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
    }

    .customer-login-check input {
        width: 16px;
        height: 16px;
    }

    .customer-login-link {
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
        text-decoration: none;
    }

    .customer-login-link:hover {
        color: #bfdbfe;
        text-decoration: underline;
    }

    .customer-login-submit {
        width: 100%;
        min-height: 48px;
        border: 0;
        border-radius: 6px;
        background: #000;
        color: #fff;
        font-size: 14px;
        font-weight: 850;
    }

    .customer-login-submit:hover,
    .customer-login-submit:focus {
        background: #111827;
        color: #fff;
    }

    .customer-login-secondary {
        margin-top: 28px;
        color: #fff;
        font-size: 13px;
        text-align: center;
    }

    .customer-login-secondary a {
        color: #fff;
        font-weight: 900;
        text-decoration: none;
    }

    .customer-login-secondary a:hover {
        text-decoration: underline;
    }

    .customer-login-footnote {
        display: flex;
        gap: 9px;
        align-items: flex-start;
        margin-top: 22px;
        padding: 12px;
        border-radius: 10px;
        background: rgba(255,255,255,.10);
        color: rgba(255,255,255,.90);
        font-size: 12px;
        line-height: 1.45;
    }

    .customer-login-footnote i {
        margin-top: 2px;
        color: #fff;
    }

    .customer-tech-support {
        margin-top: 12px;
        text-align: center;
    }

    .customer-tech-support a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 8px 16px;
        border: 1px solid rgba(255,255,255,.75);
        border-radius: 6px;
        color: #fff;
        font-size: 13px;
        font-weight: 850;
        text-decoration: none;
    }

    .customer-tech-support a:hover,
    .customer-tech-support a:focus {
        background: #fff;
        color: var(--login-primary);
    }

    @media (max-width: 900px) {
        .customer-login-shell { padding: 18px; }
        .customer-login-card { flex-direction: column; min-height: 0; }
        .customer-login-left, .customer-login-right { width: 100%; }
        .customer-login-left, .customer-login-right { padding: 34px 24px; }
        .customer-login-logo { width: 126px; height: 126px; margin-bottom: 22px; }
        .customer-login-title, .customer-login-welcome { font-size: 26px; }
        .customer-login-options { align-items: flex-start; flex-direction: column; }
    }

    @media (max-width: 575px) {
        .customer-login-shell { padding: 0; align-items: stretch; }
        .customer-login-card { border-radius: 0; min-height: 100vh; }
        .customer-login-left, .customer-login-right { padding: 30px 18px; }
    }
</style>

<div class="customer-login-shell">
    <div class="customer-login-card">
        <section class="customer-login-left">
            <div class="customer-login-logo">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }} logo">
                @else
                    <div class="customer-login-logo-mark">{{ $brandInitials }}</div>
                @endif
            </div>

            <h1 class="customer-login-title">{{ $loginHeadline }}</h1>
            <p class="customer-login-intro">{{ $loginIntro }}</p>

            <div class="customer-login-features">
                @foreach($loginFeatures as $feature)
                    @if(filled($feature['text']))
                        <div class="customer-login-feature">
                            <i class="fas {{ $feature['icon'] }}"></i>
                            <span>{{ $feature['text'] }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="customer-login-right">
            <div class="customer-login-form-inner">
                <div class="customer-login-welcome">{{ $tenantContext ? 'Welcome to '.$brandName : 'Welcome back' }}</div>
                <div class="customer-login-subtitle">
                    {{ $tenantContext ? 'Sign in to your account.' : 'Sign in to continue to' }}
                    @unless($tenantContext)<strong>{{ $brandName }}</strong>.@endunless
                </div>

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
                        <label for="email" class="form-label">Email or Mailbox</label>
                        <input type="text" class="form-control {{ isset($errors) && $errors->has('email') ? 'is-invalid' : '' }}" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        @if(isset($errors) && $errors->has('email'))
                            <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                        @endif
                    </div>

                    <div>
                        <label for="password" class="form-label">Password</label>
                        <div class="customer-password-wrap">
                            <input type="password" class="form-control pe-5 {{ isset($errors) && $errors->has('password') ? 'is-invalid' : '' }}" id="password" name="password" required>
                            <button type="button" class="customer-password-toggle" aria-label="Show password" data-password-toggle><i class="fas fa-eye"></i></button>
                        </div>
                        @if(isset($errors) && $errors->has('password'))
                            <div class="invalid-feedback d-block">{{ $errors->first('password') }}</div>
                        @endif
                    </div>

                    <div class="customer-login-options">
                        <label class="customer-login-check" for="remember">
                            <input type="checkbox" name="remember" value="1" id="remember" @checked(old('remember'))>
                            <span>Remember me</span>
                        </label>

                        <a href="{{ route('password.request') }}" class="customer-login-link">Forgot your password?</a>
                    </div>

                    <button type="submit" class="btn customer-login-submit">Sign in</button>

                    <div class="customer-login-secondary">
                        @if($tenantContext)
                            Don't have an account? <a href="{{ route('customer.register') }}">Sign up</a>
                        @elseif($courierRegistrationEnabled)
                            New courier company? <a href="{{ route('tenant.register') }}">Register and choose a plan</a>
                        @endif
                    </div>

                    @if($tenantContext)
                        <div class="customer-tech-support">
                            <a href="{{ route('tech-support.login') }}">
                                <i class="fas fa-headset"></i>
                                Tech Support
                            </a>
                        </div>
                    @endif

                    <div class="customer-login-footnote">
                        <i class="fas fa-lock"></i>
                        <span>{{ $tenantContext ? 'Courier-aware login keeps staff and customer accounts inside the correct portal.' : 'Platform login supports System Admin, courier, staff, and customer accounts.' }}</span>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('[data-password-toggle]');
    const input = document.getElementById('password');

    if (!toggle || !input) return;

    toggle.addEventListener('click', function () {
        input.type = input.type === 'password' ? 'text' : 'password';
        toggle.innerHTML = input.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
});
</script>
@endsection
