@extends('layouts.app')

@section('title', 'Customer Registration')

@section('content')
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $settings = $platformSettings ?? collect();
    $logoUrl = function (?string $path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    };
    $tenantSettings = $courier
        ? \App\Models\TenantSetting::where('tenant_id', $courier->id)->whereIn('key', [
            'brand_primary_color',
            'brand_secondary_color',
            'login_kicker',
            'login_headline',
            'login_intro_text',
            'login_feature_1',
            'login_feature_2',
            'login_feature_3',
            'login_panel_image_url',
            'login_background_url',
        ])->pluck('value', 'key')
        : collect();
    $brandName = optional($courier)->company_name ?: ($platformName ?? config('app.name', 'Courier SaaS'));
    $primary = $tenantSettings['brand_primary_color'] ?? ($settings['brand_primary_color'] ?? '#14b8a6');
    $primaryStrong = $tenantSettings['brand_secondary_color'] ?? ($settings['brand_secondary_color'] ?? '#0f766e');
    $logo = $logoUrl(optional($courier)->logo);
    $panelImage = $logoUrl($tenantSettings['login_panel_image_url'] ?? null);
    $backgroundImage = $logoUrl($tenantSettings['login_background_url'] ?? null);
    $headline = $tenantSettings['login_headline'] ?? 'Create your account';
    $intro = $tenantSettings['login_intro_text'] ?? 'Join us for fast, reliable shipping from the USA and China to Jamaica.';
    $kicker = $tenantSettings['login_kicker'] ?? 'Customer signup';
    $features = [
        $tenantSettings['login_feature_1'] ?? 'Get a free mailing address and consolidate your packages.',
        $tenantSettings['login_feature_2'] ?? 'Commercial rates, bulk shipping, and cargo logistics for businesses.',
        $tenantSettings['login_feature_3'] ?? 'Track packages, invoices, pickup, and delivery updates from your dashboard.',
    ];
    $oldFullName = trim((string) old('full_name'));
    $oldNameParts = preg_split('/\s+/', $oldFullName, 2) ?: [];
    $oldFirstName = old('first_name', $oldNameParts[0] ?? '');
    $oldLastName = old('last_name', $oldNameParts[1] ?? '');
    $selectedTenantId = old('tenant_id', optional($courier)->id);
    $outlets = $courier
        ? \App\Models\Outlet::where('tenant_id', $courier->id)->orderByDesc('is_main')->orderBy('name')->get()
        : collect();
@endphp

<style>
    .customer-register-ui {
        --register-brand: {{ $primary }};
        --register-brand-strong: {{ $primaryStrong }};
        background:
            @if($backgroundImage)
                linear-gradient(rgba(248, 250, 252, .88), rgba(248, 250, 252, .94)),
                url('{{ $backgroundImage }}') center/cover no-repeat,
            @endif
            #f8fafc;
        color: #1f2937;
        font-family: Inter, "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        min-height: 100vh;
        overflow-x: hidden;
        padding: 48px 16px;
    }
    .customer-register-ui * { box-sizing: border-box; letter-spacing: 0; }
    .customer-register-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        box-shadow: 0 25px 55px rgba(15, 23, 42, .13);
        margin: 0 auto;
        max-width: 768px;
        overflow: hidden;
        padding: 32px;
        position: relative;
        width: 100%;
    }
    .customer-register-card::before {
        background: linear-gradient(135deg, var(--register-brand), transparent 65%);
        content: "";
        height: 5px;
        inset: 0 0 auto;
        position: absolute;
    }
    .register-header { text-align: center; }
    .register-logo {
        align-items: center;
        display: flex;
        height: 80px;
        justify-content: center;
        margin: 0 auto 24px;
        max-width: 240px;
        overflow: hidden;
        width: 100%;
    }
    .register-logo img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .register-logo-fallback {
        align-items: center;
        background: color-mix(in srgb, var(--register-brand) 14%, white);
        border-radius: 12px;
        color: var(--register-brand-strong);
        display: flex;
        font-size: 28px;
        font-weight: 800;
        height: 72px;
        justify-content: center;
        width: 120px;
    }
    .register-kicker-line {
        color: var(--register-brand-strong);
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .register-title {
        color: #111827;
        font-size: clamp(28px, 4vw, 34px);
        font-weight: 850;
        line-height: 1.15;
        margin: 0;
    }
    .register-subtitle {
        color: #64748b;
        font-size: 14px;
        margin: 10px auto 0;
        max-width: 560px;
    }
    .register-panel-image {
        border-radius: 12px;
        height: 120px;
        margin: 24px auto 0;
        max-width: 520px;
        overflow: hidden;
    }
    .register-panel-image img { height: 100%; object-fit: cover; width: 100%; }
    .register-progress {
        margin: 32px auto 48px;
        max-width: 576px;
        padding: 0 8px;
        width: 100%;
    }
    .register-progress-icons {
        display: flex;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .register-progress-icon {
        align-items: center;
        color: #94a3b8;
        display: flex;
        flex-direction: column;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        transition: color .3s ease;
        width: 56px;
    }
    .register-progress-icon i { font-size: 18px; margin-bottom: 4px; }
    .register-progress-icon.active { color: var(--register-brand-strong); }
    .register-track {
        align-items: center;
        background: #e5e7eb;
        border-radius: 999px;
        display: flex;
        height: 8px;
        position: relative;
    }
    .register-progress-fill {
        background: var(--register-brand);
        border-radius: inherit;
        height: 100%;
        left: 0;
        position: absolute;
        top: 0;
        transition: width .5s ease;
        width: 0;
    }
    .register-plane {
        color: var(--register-brand-strong);
        filter: drop-shadow(0 6px 8px rgba(15, 23, 42, .18));
        font-size: 30px;
        left: 0;
        margin-left: -15px;
        position: absolute;
        top: -15px;
        transition: left .6s cubic-bezier(.4,0,.2,1), transform .6s ease, opacity .6s ease;
        z-index: 2;
    }
    .register-plane.takeoff {
        opacity: .25;
        transform: translate(90px, -50px) rotate(-35deg) scale(1.35);
    }
    .register-step {
        opacity: 1;
        position: relative;
        transform: translateY(0);
        transition: opacity .3s ease, transform .3s ease;
    }
    .register-step.hidden-step {
        opacity: 0;
        pointer-events: none;
        position: absolute;
        transform: translateY(10px);
        visibility: hidden;
    }
    .register-step h3 {
        border-bottom: 1px solid #e5e7eb;
        color: #111827;
        font-size: 18px;
        font-weight: 750;
        margin: 0 0 24px;
        padding-bottom: 10px;
    }
    .register-type-grid {
        display: grid;
        gap: 24px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .register-type-card {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 16px;
        cursor: pointer;
        display: flex;
        min-height: 185px;
        padding: 24px;
        position: relative;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }
    .register-type-card.active {
        border-color: var(--register-brand);
        box-shadow: 0 0 0 2px var(--register-brand);
    }
    .register-type-card:hover { border-color: color-mix(in srgb, var(--register-brand) 40%, #d1d5db); }
    .register-type-title {
        align-items: center;
        color: #111827;
        display: flex;
        font-size: 18px;
        font-weight: 750;
        gap: 9px;
        margin-bottom: 8px;
    }
    .register-type-title i { color: var(--register-brand); }
    .register-type-card p { color: #64748b; font-size: 14px; line-height: 1.5; margin: 0; }
    .register-type-check {
        color: var(--register-brand-strong);
        font-size: 22px;
        position: absolute;
        right: 20px;
        top: 20px;
    }
    .register-type-card:not(.active) .register-type-check { display: none; }
    .register-section-soft {
        background: color-mix(in srgb, var(--register-brand) 9%, white);
        border: 1px solid color-mix(in srgb, var(--register-brand) 20%, white);
        border-radius: 12px;
        margin-bottom: 28px;
        padding: 24px;
    }
    .register-grid {
        display: grid;
        gap: 22px 16px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }
    .register-col-2 { grid-column: span 2; }
    .register-col-3 { grid-column: span 3; }
    .register-col-4 { grid-column: span 4; }
    .register-col-6 { grid-column: span 6; }
    .customer-register-ui label {
        color: #374151;
        display: block;
        font-size: 14px;
        font-weight: 650;
        margin-bottom: 6px;
    }
    .required-asterisk::after {
        color: #ef4444;
        content: "*";
        margin-left: 4px;
    }
    .register-input,
    .register-select {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
        color: #111827;
        display: block;
        font-size: 14px;
        min-height: 43px;
        outline: none;
        padding: 10px 12px;
        transition: border-color .15s ease, box-shadow .15s ease;
        width: 100%;
    }
    .register-input:focus,
    .register-select:focus {
        border-color: var(--register-brand);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--register-brand) 22%, transparent);
    }
    .register-help { color: #64748b; font-size: 12px; margin-top: 5px; }
    .register-upload {
        align-items: center;
        background: #fff;
        border: 2px dashed color-mix(in srgb, var(--register-brand) 38%, #cbd5e1);
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        height: 128px;
        justify-content: center;
        text-align: center;
        transition: background .15s ease;
        width: 100%;
    }
    .register-upload:hover { background: color-mix(in srgb, var(--register-brand) 7%, white); }
    .register-upload i { color: var(--register-brand); font-size: 30px; margin-bottom: 10px; }
    .register-upload strong { color: var(--register-brand-strong); }
    .register-upload span { color: #64748b; display: block; font-size: 12px; margin-top: 3px; }
    .register-terms {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        margin-top: 28px;
        padding: 16px;
    }
    .register-terms-row { align-items: flex-start; display: flex; gap: 12px; }
    .register-terms input { height: 20px; margin-top: 1px; width: 20px; }
    .register-alert {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #b91c1c;
        font-size: 14px;
        margin-bottom: 20px;
        padding: 12px;
        text-align: center;
    }
    .register-nav {
        align-items: center;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin-top: 32px;
        padding-top: 24px;
    }
    .register-nav-spacer { flex: 1; }
    .register-btn {
        align-items: center;
        border-radius: 8px;
        border: 1px solid transparent;
        display: inline-flex;
        font-size: 14px;
        font-weight: 750;
        gap: 8px;
        justify-content: center;
        min-height: 42px;
        padding: 10px 28px;
        transition: background .16s ease, border-color .16s ease, transform .16s ease;
    }
    .register-btn-primary {
        background: var(--register-brand-strong);
        box-shadow: 0 8px 18px color-mix(in srgb, var(--register-brand) 24%, transparent);
        color: #fff;
    }
    .register-btn-primary:hover { background: var(--register-brand); color: #fff; transform: translateY(-1px); }
    .register-btn-secondary {
        background: #fff;
        border-color: #d1d5db;
        color: #374151;
    }
    .register-btn-secondary:hover { background: #f8fafc; color: #111827; }
    .register-bottom-link {
        color: #64748b;
        font-size: 14px;
        margin-top: 18px;
        text-align: center;
    }
    .register-bottom-link a { color: var(--register-brand-strong); font-weight: 750; }
    .register-feature-strip {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-top: 24px;
    }
    .register-feature-chip {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        color: #475569;
        font-size: 13px;
        padding: 12px;
    }
    .register-feature-chip i { color: var(--register-brand); margin-right: 7px; }
    .d-none { display: none !important; }
    @media (max-width: 720px) {
        .customer-register-ui { padding: 24px 12px; }
        .customer-register-card { padding: 24px 16px; }
        .register-type-grid,
        .register-feature-strip { grid-template-columns: 1fr; }
        .register-col-2,
        .register-col-3,
        .register-col-4,
        .register-col-6 { grid-column: span 6; }
        .register-progress-icon span { font-size: 10px; }
        .register-nav { align-items: stretch; flex-direction: column; }
        .register-nav-spacer { display: none; }
        .register-btn { width: 100%; }
    }
</style>

<div class="customer-register-ui">
    <div class="customer-register-card">
        <div class="register-header" id="registerHeader">
            <div class="register-logo">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $brandName }} logo">
                @else
                    <div class="register-logo-fallback">{{ collect(explode(' ', $brandName))->filter()->take(2)->map(fn ($word) => strtoupper(substr($word, 0, 1)))->implode('') ?: 'CP' }}</div>
                @endif
            </div>
            <div class="register-kicker-line">{{ $kicker }}</div>
            <h1 class="register-title">{{ $headline }}</h1>
            <p class="register-subtitle">{{ $intro }}</p>
            @if($panelImage)
                <div class="register-panel-image"><img src="{{ $panelImage }}" alt="{{ $brandName }} registration"></div>
            @endif
            <div class="register-feature-strip">
                @foreach($features as $index => $feature)
                    <div class="register-feature-chip"><i class="fas {{ ['fa-box-open', 'fa-briefcase', 'fa-location-dot'][$index] }}"></i>{{ $feature }}</div>
                @endforeach
            </div>
        </div>

        <div class="register-progress" id="registerProgress">
            <div class="register-progress-icons">
                <div class="register-progress-icon active" data-step-icon="1"><i class="fas fa-user"></i><span>Type</span></div>
                <div class="register-progress-icon" data-step-icon="2"><i class="fas fa-file-lines"></i><span>Details</span></div>
                <div class="register-progress-icon" data-step-icon="3"><i class="fas fa-location-dot"></i><span>Location</span></div>
                <div class="register-progress-icon" data-step-icon="4"><i class="fas fa-lock"></i><span>Secure</span></div>
            </div>
            <div class="register-track">
                <div class="register-progress-fill" id="registerProgressFill"></div>
                <div class="register-plane" id="registerPlane"><i class="fas fa-paper-plane"></i></div>
            </div>
        </div>

        @if($errors->any())
            <div class="register-alert">
                <strong>Please check registration.</strong> {{ $errors->first() }}
            </div>
        @endif
        <div class="register-alert d-none" id="registerStepError">Please fill out all required fields correctly to proceed.</div>

        <form method="POST" action="{{ route('customer.register.store') }}" id="customerRegistrationForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="full_name" id="full_name" value="{{ old('full_name') }}">

            <div class="register-step" data-register-step="1">
                <h3 class="text-center">What type of account do you need?</h3>
                <div class="register-type-grid">
                    <label class="register-type-card active" id="labelPersonal">
                        <input type="radio" name="account_type" value="personal" class="d-none" checked>
                        <span>
                            <span class="register-type-title"><i class="fas fa-user"></i>Personal Account</span>
                            <p>Ideal for individuals shopping online from the USA or China for personal use. Get a free mailing address and consolidate your packages.</p>
                        </span>
                        <i class="fas fa-circle-check register-type-check"></i>
                    </label>
                    <label class="register-type-card" id="labelBusiness">
                        <input type="radio" name="account_type" value="business" class="d-none">
                        <span>
                            <span class="register-type-title"><i class="fas fa-briefcase"></i>Business Account</span>
                            <p>Perfect for commercial importers and e-commerce stores. Benefit from commercial rates, bulk shipping, and heavy cargo logistics.</p>
                        </span>
                        <i class="fas fa-circle-check register-type-check"></i>
                    </label>
                </div>
            </div>

            <div class="register-step hidden-step" data-register-step="2">
                <div class="register-section-soft d-none" id="businessSection">
                    <h3><i class="fas fa-building me-2"></i>Company Information</h3>
                    <div class="register-grid">
                        <div class="register-col-6">
                            <label for="company_name" class="required-asterisk">Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="register-input" value="{{ old('company_name') }}">
                        </div>
                        <div class="register-col-3">
                            <label for="company_registration" class="required-asterisk">Company Registration Number</label>
                            <input type="text" id="company_registration" name="company_registration" class="register-input" value="{{ old('company_registration') }}">
                        </div>
                        <div class="register-col-3">
                            <label for="representative_role" class="required-asterisk">Your Role in Company</label>
                            <input type="text" id="representative_role" name="representative_role" class="register-input" value="{{ old('representative_role') }}" placeholder="e.g., Director, Purchasing Manager">
                        </div>
                        <div class="register-col-6">
                            <label for="government_id_document" class="required-asterisk">Upload Government ID</label>
                            <label for="government_id_document" class="register-upload">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <div><strong>Click to upload</strong> or drag and drop</div>
                                <span>PDF, JPG, or PNG (MAX. 5MB)</span>
                            </label>
                            <input id="government_id_document" name="government_id_document" type="file" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="governmentIdFileNameDisplay" class="register-help d-none"></div>
                        </div>
                        <div class="register-col-6">
                            <label for="tax_document" class="required-asterisk">Upload TRN / EIN Document</label>
                            <label for="tax_document" class="register-upload">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <div><strong>Click to upload</strong> or drag and drop</div>
                                <span>PDF, JPG, or PNG (MAX. 5MB)</span>
                            </label>
                            <input id="tax_document" name="tax_document" type="file" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="taxDocumentFileNameDisplay" class="register-help d-none"></div>
                        </div>
                        <div class="register-col-6">
                            <label for="business_doc" class="required-asterisk">Upload Business Registration Certificate</label>
                            <label for="business_doc" class="register-upload">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <div><strong>Click to upload</strong> or drag and drop</div>
                                <span>PDF, JPG, or PNG (MAX. 5MB)</span>
                            </label>
                            <input id="business_doc" name="business_doc" type="file" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="businessDocumentFileNameDisplay" class="register-help d-none"></div>
                        </div>
                    </div>
                </div>

                <h3>Personal Details</h3>
                <div class="register-grid">
                    @if($courier)
                        <input type="hidden" name="tenant_id" value="{{ $courier->id }}">
                        <div class="register-col-6">
                            <label>Registering With</label>
                            <div class="register-input" style="background:#f8fafc;">{{ $courier->company_name }}</div>
                        </div>
                    @else
                        <div class="register-col-6">
                            <label for="tenant_id" class="required-asterisk">Courier Company</label>
                            <select id="tenant_id" name="tenant_id" class="register-select @error('tenant_id') is-invalid @enderror" required>
                                <option value="">Select courier</option>
                                @foreach($couriers as $tenant)
                                    <option value="{{ $tenant->id }}" @selected($selectedTenantId == $tenant->id)>{{ $tenant->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="register-col-2">
                        <label for="title" class="required-asterisk">Title</label>
                        <select id="title" name="title" class="register-select">
                            <option value="">Select</option>
                            @foreach(['Mr.', 'Ms.', 'Mrs.', 'Dr.'] as $title)
                                <option value="{{ $title }}" @selected(old('title') === $title)>{{ $title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="register-col-2">
                        <label for="first_name" class="required-asterisk">First name</label>
                        <input type="text" id="first_name" name="first_name" class="register-input" value="{{ $oldFirstName }}">
                    </div>
                    <div class="register-col-2">
                        <label for="last_name" class="required-asterisk">Last name</label>
                        <input type="text" id="last_name" name="last_name" class="register-input" value="{{ $oldLastName }}">
                    </div>
                    <div class="register-col-3">
                        <label for="email" class="required-asterisk">Email address</label>
                        <input type="email" id="email" name="email" autocomplete="email" class="register-input @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    </div>
                    <div class="register-col-3">
                        <label for="phone" class="required-asterisk">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="register-input @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                    </div>
                    <div class="register-col-6">
                        <label for="trn" class="required-asterisk">ID Number (TRN / SSN)</label>
                        <input type="text" id="trn" name="trn" class="register-input @error('trn') is-invalid @enderror" value="{{ old('trn') }}" placeholder="Required for customs clearance">
                        <div class="register-help">Valid identification is required for processing international shipments through customs.</div>
                    </div>
                </div>
            </div>

            <div class="register-step hidden-step" data-register-step="3">
                <h3>Delivery Address & Branch</h3>
                <div class="register-grid">
                    <div class="register-col-3">
                        <label for="country" class="required-asterisk">Country</label>
                        <select id="country" name="country" class="register-select">
                            <option value="Jamaica" selected>Jamaica</option>
                            <option value="USA">United States</option>
                            <option value="Canada">Canada</option>
                            <option value="UK">United Kingdom</option>
                        </select>
                    </div>
                    <div class="register-col-3">
                        <label for="parish" id="regionLabel" class="required-asterisk">Parish</label>
                        <select id="parish" name="parish" class="register-select @error('parish') is-invalid @enderror" data-old="{{ old('parish') }}"></select>
                    </div>
                    <div class="register-col-3">
                        <label for="city" class="required-asterisk">City / Town</label>
                        <input type="text" id="city" name="city" class="register-input @error('city') is-invalid @enderror" value="{{ old('city') }}">
                    </div>
                    <div class="register-col-3">
                        <label for="branch_select" class="required-asterisk">Preferred Branch</label>
                        <select id="branch_select" name="branch_select" class="register-select">
                            <option value="">Select a branch location</option>
                            @forelse($outlets as $outlet)
                                <option value="{{ $outlet->id }}" @selected(old('branch_select') == $outlet->id)>{{ $outlet->name }}</option>
                            @empty
                                <option value="main">Main Branch</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="register-col-6">
                        <label for="address_line1" class="required-asterisk">Full Address</label>
                        <input type="text" id="address_line1" name="address_line1" class="register-input @error('address_line1') is-invalid @enderror" value="{{ old('address_line1') }}" placeholder="Street address, apartment, suite, etc.">
                    </div>
                </div>
            </div>

            <div class="register-step hidden-step" data-register-step="4">
                <h3>Security & Final Steps</h3>
                <div class="register-grid">
                    <div class="register-col-3">
                        <label for="password" class="required-asterisk">Password</label>
                        <input type="password" id="password" name="password" class="register-input @error('password') is-invalid @enderror">
                    </div>
                    <div class="register-col-3">
                        <label for="password_confirmation" class="required-asterisk">Confirm Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="register-input">
                    </div>
                    <div class="register-col-6">
                        <label for="referral_code">Referral Code (Optional)</label>
                        <input type="text" id="referral_code" name="referral_code" class="register-input text-uppercase @error('referral_code') is-invalid @enderror" value="{{ old('referral_code', request('ref') ?: request('referral_code') ?: request('api_key')) }}" placeholder="Did someone refer you?">
                    </div>
                </div>
                <div class="register-terms">
                    <div class="register-terms-row">
                        <input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms'))>
                        <div>
                            <label for="terms" class="required-asterisk">I agree to create a customer account with the selected courier.</label>
                            <div class="register-help">You must agree to the terms to complete registration.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="register-nav">
                <button type="button" id="registerPrev" class="register-btn register-btn-secondary d-none"><i class="fas fa-arrow-left"></i>Back</button>
                <div class="register-nav-spacer"></div>
                <button type="button" id="registerNext" class="register-btn register-btn-primary">Next Step<i class="fas fa-arrow-right"></i></button>
                <button type="submit" id="registerSubmit" class="register-btn register-btn-primary d-none"><i class="fas fa-user-plus"></i>Complete Registration</button>
            </div>
        </form>

        <div class="register-bottom-link">
            Already have an account? <a href="{{ route('login') }}">Sign in here</a>
        </div>
    </div>
</div>

<script>
    (function () {
        const regionsData = {
            Jamaica: ['Kingston', 'St. Andrew', 'St. Thomas', 'Portland', 'St. Mary', 'St. Ann', 'Trelawny', 'St. James', 'Hanover', 'Westmoreland', 'St. Elizabeth', 'Manchester', 'Clarendon', 'St. Catherine'],
            USA: ['Florida', 'New York', 'Georgia', 'Texas', 'California', 'Other State...'],
            Canada: ['Ontario', 'Quebec', 'Nova Scotia', 'Other Province...'],
            UK: ['England', 'Scotland', 'Wales', 'Northern Ireland']
        };
        const form = document.getElementById('customerRegistrationForm');
        const totalSteps = 4;
        let currentStep = 1;
        let isBusiness = false;
        const stepError = document.getElementById('registerStepError');
        const progressFill = document.getElementById('registerProgressFill');
        const plane = document.getElementById('registerPlane');
        const prevButton = document.getElementById('registerPrev');
        const nextButton = document.getElementById('registerNext');
        const submitButton = document.getElementById('registerSubmit');
        const businessSection = document.getElementById('businessSection');
        const countrySelect = document.getElementById('country');
        const parishSelect = document.getElementById('parish');

        function setFullName() {
            const first = document.getElementById('first_name').value.trim();
            const last = document.getElementById('last_name').value.trim();
            document.getElementById('full_name').value = [first, last].filter(Boolean).join(' ');
        }

        function updateRegions() {
            const selectedCountry = countrySelect.value;
            const oldRegion = parishSelect.dataset.old;
            parishSelect.innerHTML = '<option value="">Select Region/Parish</option>';
            (regionsData[selectedCountry] || []).forEach(function (region) {
                const option = document.createElement('option');
                option.value = region;
                option.textContent = region;
                if (oldRegion === region) {
                    option.selected = true;
                }
                parishSelect.appendChild(option);
            });
            document.getElementById('regionLabel').textContent = selectedCountry === 'Jamaica' ? 'Parish' : 'State/Province';
            document.getElementById('regionLabel').classList.add('required-asterisk');
        }

        function setAccountType(type) {
            isBusiness = type === 'business';
            document.getElementById('labelPersonal').classList.toggle('active', !isBusiness);
            document.getElementById('labelBusiness').classList.toggle('active', isBusiness);
            businessSection.classList.toggle('d-none', !isBusiness);
        }

        function setRequiredFields() {
            form.querySelectorAll('input, select, textarea').forEach(function (input) {
                if (!['tenant_id'].includes(input.name)) {
                    input.removeAttribute('required');
                }
            });
            if (currentStep === 2) {
                ['title', 'first_name', 'last_name', 'email', 'phone', 'trn'].forEach(function (id) {
                    document.getElementById(id)?.setAttribute('required', 'required');
                });
                if (isBusiness) {
                    ['company_name', 'company_registration', 'representative_role', 'government_id_document', 'tax_document', 'business_doc'].forEach(function (id) {
                        document.getElementById(id)?.setAttribute('required', 'required');
                    });
                }
            }
            if (currentStep === 3) {
                ['country', 'parish', 'city', 'address_line1', 'branch_select'].forEach(function (id) {
                    document.getElementById(id)?.setAttribute('required', 'required');
                });
            }
            if (currentStep === 4) {
                ['password', 'password_confirmation', 'terms'].forEach(function (id) {
                    document.getElementById(id)?.setAttribute('required', 'required');
                });
            }
        }

        function updateUI() {
            document.querySelectorAll('[data-register-step]').forEach(function (step) {
                step.classList.toggle('hidden-step', Number(step.dataset.registerStep) !== currentStep);
            });
            document.querySelectorAll('[data-step-icon]').forEach(function (icon) {
                icon.classList.toggle('active', Number(icon.dataset.stepIcon) <= currentStep);
            });
            const percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = percentage + '%';
            plane.style.left = percentage + '%';
            prevButton.classList.toggle('d-none', currentStep === 1);
            nextButton.classList.toggle('d-none', currentStep === totalSteps);
            submitButton.classList.toggle('d-none', currentStep !== totalSteps);
            stepError.classList.add('d-none');
            setRequiredFields();
        }

        function validateCurrentStep() {
            setFullName();
            setRequiredFields();
            const step = document.querySelector('[data-register-step="' + currentStep + '"]');
            const requiredFields = step.querySelectorAll('input[required], select[required], textarea[required]');
            for (const field of requiredFields) {
                if ((field.type === 'checkbox' && !field.checked) || (field.type !== 'checkbox' && !field.value)) {
                    stepError.classList.remove('d-none');
                    field.reportValidity();
                    return false;
                }
            }
            if (currentStep === 4 && document.getElementById('password').value !== document.getElementById('password_confirmation').value) {
                stepError.textContent = 'Passwords do not match.';
                stepError.classList.remove('d-none');
                document.getElementById('password_confirmation').reportValidity();
                return false;
            }
            stepError.textContent = 'Please fill out all required fields correctly to proceed.';
            return true;
        }

        document.getElementById('labelPersonal').addEventListener('click', function () { setAccountType('personal'); });
        document.getElementById('labelBusiness').addEventListener('click', function () { setAccountType('business'); });
        countrySelect.addEventListener('change', updateRegions);
        [
            ['government_id_document', 'governmentIdFileNameDisplay'],
            ['tax_document', 'taxDocumentFileNameDisplay'],
            ['business_doc', 'businessDocumentFileNameDisplay'],
        ].forEach(function ([inputId, displayId]) {
            document.getElementById(inputId)?.addEventListener('change', function (event) {
                const fileNameDisplay = document.getElementById(displayId);
                if (event.target.files.length > 0) {
                    fileNameDisplay.textContent = 'File attached: ' + event.target.files[0].name;
                    fileNameDisplay.classList.remove('d-none');
                } else {
                    fileNameDisplay.classList.add('d-none');
                }
            });
        });
        nextButton.addEventListener('click', function () {
            if (! validateCurrentStep()) {
                return;
            }
            currentStep++;
            updateUI();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        prevButton.addEventListener('click', function () {
            currentStep--;
            updateUI();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        form.addEventListener('submit', function (event) {
            if (! validateCurrentStep()) {
                event.preventDefault();
                return;
            }
            plane.classList.add('takeoff');
        });
        ['first_name', 'last_name'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', setFullName);
        });
        updateRegions();
        updateUI();
        setFullName();
    })();
</script>
@endsection
