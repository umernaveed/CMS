@extends('layouts.app')

@section('title', 'Courier Settings')

@section('content')
@php
    $canUsePaymentsModule = auth()->user()->subscriptionAllowsModule('payments');
@endphp
<style>
    .settings-panel {
        overflow: hidden;
        padding: 1rem !important;
    }
    .settings-panel .form-label {
        font-size: 0.74rem;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.22rem;
    }
    .settings-panel .form-control,
    .settings-panel .form-select {
        min-height: 36px;
        padding-top: .38rem;
        padding-bottom: .38rem;
        font-size: 0.86rem;
    }
    .settings-panel textarea.form-control {
        min-height: 68px;
    }
    .settings-panel .email-editor-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        width: 100%;
    }
    .settings-panel .email-editor-toolbar .btn,
    .settings-panel .email-editor-toolbar .form-select {
        flex: 0 0 auto;
    }
    .settings-panel .email-editor-toolbar .form-select {
        width: auto;
        min-width: 118px;
    }
    .settings-panel [data-rich-editor],
    .settings-panel #email_footer_editor {
        width: 100%;
        max-width: 100%;
        min-height: 150px;
        overflow: auto;
        resize: vertical;
    }
    .settings-panel [contenteditable="true"] table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .settings-panel [contenteditable="true"] td,
    .settings-panel [contenteditable="true"] th {
        border: 1px solid #cbd5e1;
        padding: 8px;
        min-width: 48px;
        vertical-align: top;
    }
    .warehouse-address-item {
        background: #f8fafc;
        border-color: #e2e8f0 !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7);
    }
    .warehouse-address-item .row {
        --bs-gutter-x: .75rem;
        --bs-gutter-y: .75rem;
    }
    .warehouse-address-item .form-control,
    .warehouse-address-item .form-select {
        background: #fff;
    }
    .warehouse-address-item textarea.form-control {
        min-height: 74px;
        resize: vertical;
    }
    .warehouse-flag-preview {
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .35rem .65rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        font-size: 1.25rem;
        line-height: 1;
    }
    .warehouse-flag-preview img {
        width: 34px;
        height: 24px;
        object-fit: cover;
        border-radius: 4px;
    }
    .settings-help {
        max-width: 760px;
        line-height: 1.45;
    }
    .settings-import-card {
        background: #fff;
        border-color: #e2e8f0 !important;
    }
    .settings-inline-import {
        flex-wrap: wrap;
        min-width: 220px;
    }
    .settings-inline-import .form-control {
        max-width: 210px;
    }
    .settings-inline-import .btn {
        white-space: nowrap;
    }
    .settings-collapse {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        margin-bottom: 10px;
        overflow: hidden;
    }
    .settings-collapse summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        cursor: pointer;
        list-style: none;
        background: #f8fafc;
        color: #0f172a;
        font-weight: 800;
    }
    .settings-collapse summary::-webkit-details-marker {
        display: none;
    }
    .settings-collapse summary::after {
        content: "\f078";
        font-family: "Font Awesome 6 Free", "Font Awesome 5 Free";
        font-weight: 900;
        color: #64748b;
        transition: transform .18s ease;
    }
    .settings-collapse:not([open]) summary::after {
        transform: rotate(-90deg);
    }
    .settings-collapse-body {
        padding: 12px;
    }
    .settings-workspace {
        display: grid;
        grid-template-columns: 228px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
    }
    .settings-menu {
        position: sticky;
        top: 92px;
        padding: 8px;
    }
    .settings-menu-title {
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        margin: 4px 8px 10px;
    }
    .settings-menu button {
        width: 100%;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 10px;
        font-weight: 800;
        text-align: left;
        font-size: 13px;
    }
    .settings-menu button i {
        width: 20px;
        color: #64748b;
        text-align: center;
    }
    .settings-menu button.active {
        background: #eff6ff;
        color: var(--brand);
    }
    .settings-menu button.active i { color: var(--brand); }
    .settings-section { display: none; }
    .settings-section.active { display: block; }
    .settings-standalone-sections {
        display: block;
        min-width: 0;
    }
    .settings-section-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 10px;
    }
    .settings-section-head h2 {
        margin: 0 0 4px;
        font-size: 18px;
        font-weight: 900;
    }
    .settings-section-head p {
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }
    .settings-save-bar {
        display: flex;
        justify-content: flex-end;
        padding-top: 4px;
    }
    .settings-maintenance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 280px));
        gap: 14px;
        align-items: stretch;
    }
    .settings-action-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
        background: #fff;
        min-height: 138px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 10px;
    }
    .settings-action-card .btn {
        width: auto !important;
        align-self: flex-start;
        min-width: 150px;
    }
    .settings-action-card .form-control {
        max-width: 280px;
    }
    .settings-tier-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .settings-tier-count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .settings-tier-list {
        display: grid;
        gap: 12px;
        margin-bottom: 16px;
    }
    .settings-tier-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        padding: 14px;
        display: grid;
        grid-template-columns: minmax(190px, 1.2fr) minmax(110px, .55fr) minmax(110px, .55fr) minmax(230px, .95fr);
        gap: 10px;
        align-items: end;
    }
    .settings-tier-card:hover {
        border-color: #bfdbfe;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
    }
    .settings-tier-name {
        display: grid;
        gap: 8px;
    }
    .settings-tier-checks {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        min-height: 32px;
        align-items: center;
    }
    .settings-tier-actions {
        display: grid;
        gap: 8px;
        justify-items: start;
    }
    .settings-tier-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .settings-tier-import {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        width: 100%;
    }
    .settings-tier-import .form-control {
        max-width: 180px;
    }
    .settings-tier-add {
        border: 1px solid #dbeafe;
        border-radius: 8px;
        background: #f8fbff;
        padding: 10px;
    }
    .settings-exchange-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 10px;
    }
    .settings-exchange-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 126px;
    }
    .settings-exchange-card h3 {
        font-size: 15px;
        font-weight: 900;
        margin: 0;
        color: #0f172a;
    }
    .settings-exchange-card p {
        margin: 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.4;
    }
    .settings-exchange-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: auto;
    }
    .settings-exchange-import {
        display: grid;
        gap: 10px;
    }
    .settings-exchange-import .form-control {
        max-width: 100%;
    }
    .settings-exchange-section {
        border-top: 1px solid #e2e8f0;
        padding-top: 12px;
        margin-top: 12px;
    }
    .settings-exchange-section-title {
        font-size: 13px;
        font-weight: 900;
        color: #475569;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .settings-alerts-section {
        max-width: 100%;
        width: 100%;
    }
    .settings-alert-layout {
        display: grid;
        grid-template-columns: minmax(280px, .85fr) minmax(0, 1.15fr);
        gap: 14px;
        align-items: start;
    }
    .settings-alert-box {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        padding: 14px;
        min-width: 0;
    }
    .settings-alert-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        padding: 14px;
        margin-bottom: 12px;
        min-width: 0;
    }
    .settings-alert-title {
        max-width: 100%;
        overflow-wrap: anywhere;
    }
    .settings-alert-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 8px;
    }
    .settings-alert-actions .btn {
        white-space: nowrap;
    }
    .settings-alert-card .row {
        --bs-gutter-x: .75rem;
        --bs-gutter-y: .75rem;
    }
    .settings-alert-card textarea,
    .settings-alert-box textarea {
        resize: vertical;
    }
    @media (max-width: 992px) {
        .settings-workspace {
            grid-template-columns: 1fr;
        }
        .settings-menu {
            position: static;
        }
        .settings-menu-buttons {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
        }
        .settings-tier-card {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .settings-tier-actions {
            grid-column: 1 / -1;
        }
        .settings-alert-layout {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 576px) {
        .settings-inline-import .form-control,
        .settings-inline-import .btn {
            max-width: none;
            width: 100%;
        }
        .settings-menu-buttons {
            grid-template-columns: 1fr;
        }
        .settings-tier-card {
            grid-template-columns: 1fr;
        }
        .settings-tier-actions {
            grid-column: auto;
        }
        .settings-tier-import .form-control,
        .settings-tier-import .btn {
            max-width: none;
            width: 100%;
        }
    }
</style>

@php
    $countryOptions = [
        'United States' => 'US',
        'China' => 'CN',
        'Jamaica' => 'JM',
        'United Kingdom' => 'GB',
        'Canada' => 'CA',
        'Panama' => 'PA',
        'Trinidad and Tobago' => 'TT',
        'Barbados' => 'BB',
        'Guyana' => 'GY',
        'Bahamas' => 'BS',
        'Dominican Republic' => 'DO',
        'Mexico' => 'MX',
        'India' => 'IN',
        'Japan' => 'JP',
        'Germany' => 'DE',
        'France' => 'FR',
        'Spain' => 'ES',
        'Italy' => 'IT',
    ];
@endphp

<div class="mb-4">
    <h1 class="h3 mb-1">General Settings</h1>
    <p class="text-muted mb-0">Configure courier settings from the internal menu.</p>
</div>

<div class="settings-workspace">
    <aside class="panel settings-menu">
        <div class="settings-menu-title">General Settings</div>
        <div class="settings-menu-buttons">
            <button type="button" class="active" data-settings-tab="warehouse"><i class="fas fa-warehouse"></i><span>System Settings</span></button>
            <button type="button" data-settings-tab="login"><i class="fas fa-right-to-bracket"></i><span>Login Page</span></button>
            <button type="button" data-settings-tab="addresses"><i class="fas fa-map-location-dot"></i><span>Address Templates</span></button>
            <button type="button" data-settings-tab="finance"><i class="fas fa-calculator"></i><span>Finance</span></button>
            <button type="button" data-settings-tab="loyalty"><i class="fas fa-gift"></i><span>Loyalty</span></button>
            @if($canUsePaymentsModule)
                <button type="button" data-settings-tab="payments"><i class="fas fa-credit-card"></i><span>Payments</span></button>
            @endif
            <button type="button" data-settings-tab="email"><i class="fas fa-envelope"></i><span>Email</span></button>
            <button type="button" data-settings-tab="support-access"><i class="fas fa-key"></i><span>Support Access</span></button>
            <button type="button" data-settings-tab="maintenance"><i class="fas fa-database"></i><span>Backup & Restore</span></button>
            <button type="button" data-settings-tab="tiers"><i class="fas fa-layer-group"></i><span>Pricing Tiers</span></button>
            <button type="button" data-settings-tab="alerts"><i class="fas fa-bell"></i><span>Login Alerts</span></button>
            <button type="button" data-settings-tab="import-export"><i class="fas fa-file-arrow-up"></i><span>Import / Export</span></button>
            <button type="button" data-settings-tab="mass-delete"><i class="fas fa-trash"></i><span>Mass Delete</span></button>
        </div>
    </aside>

    <div>
<form id="courierSettingsFormPanel" method="POST" action="{{ route('courier.settings.update') }}" enctype="multipart/form-data" class="panel settings-panel p-4 mb-4">
    @csrf @method('PUT')
    <details class="settings-collapse settings-section active" data-settings-section="warehouse" open>
        <summary>Warehouse Addresses</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>System Settings</h2><p>Configure mailbox and courier code defaults.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Mailbox Prefix</label><input name="mailbox_prefix" class="form-control text-uppercase" value="{{ old('mailbox_prefix', $settings['mailbox_prefix'] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', auth()->user()->tenant->company_name ?? 'COU'), 0, 3))) }}" placeholder="OL-"></div>
        <div class="col-md-3"><label class="form-label">Courier Code / HAWB Prefix</label><input name="hawb_prefix" class="form-control text-uppercase" value="{{ old('hawb_prefix', $settings['hawb_prefix'] ?? '') }}" placeholder="NCLS"></div>
        <div class="col-md-3"><label class="form-label">Primary Brand Color</label><input type="color" name="brand_primary_color" class="form-control form-control-color w-100" value="{{ old('brand_primary_color', $settings['brand_primary_color'] ?? '#135FD2') }}"></div>
        <div class="col-md-3"><label class="form-label">Secondary Brand Color</label><input type="color" name="brand_secondary_color" class="form-control form-control-color w-100" value="{{ old('brand_secondary_color', $settings['brand_secondary_color'] ?? '#073B70') }}"></div>
        <div class="col-md-4"><label class="form-label">Invoice Logo</label><input type="file" name="invoice_logo_upload" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
        @if(! empty($settings['invoice_logo']))
            <div class="col-md-3">
                <label class="form-label">Current Invoice Logo</label>
                <div class="border rounded bg-white p-2">
                    <img src="{{ \Illuminate\Support\Str::startsWith($settings['invoice_logo'], ['http://', 'https://']) ? $settings['invoice_logo'] : asset(ltrim($settings['invoice_logo'], '/')) }}" alt="Invoice logo" style="height:44px; max-width:160px; object-fit:contain;">
                </div>
            </div>
        @endif
        <div class="col-md-5 d-flex align-items-end"><div class="text-muted small pb-2 settings-help">New packages use the HAWB prefix first, then mailbox prefix if blank. Example: NCLS000001.</div></div>
    </div>
        </div>
    </details>

    <details class="settings-collapse settings-section" data-settings-section="login" open>
        <summary>Courier Admin Login Page</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>Login Page</h2><p>Customize the courier admin login screen.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Courier Logo</label><input type="file" name="tenant_logo_upload" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
        @if(! empty(auth()->user()->tenant->logo))
            <div class="col-md-4">
                <label class="form-label">Current Courier Logo</label>
                <div class="border rounded bg-white p-2">
                    <img src="{{ \Illuminate\Support\Str::startsWith(auth()->user()->tenant->logo, ['http://', 'https://']) ? auth()->user()->tenant->logo : asset(ltrim(auth()->user()->tenant->logo, '/')) }}" alt="Current courier logo" style="height:54px; max-width:180px; object-fit:contain;">
                </div>
            </div>
        @endif
        <div class="col-md-4"><label class="form-label">Small Kicker Text</label><input name="login_kicker" class="form-control" value="{{ old('login_kicker', $settings['login_kicker'] ?? '') }}" placeholder="Secure courier staff access"></div>
        <div class="col-md-4"><label class="form-label">Headline</label><input name="login_headline" class="form-control" value="{{ old('login_headline', $settings['login_headline'] ?? '') }}" placeholder="Courier operations workspace"></div>
        <div class="col-md-4"><label class="form-label">Panel Image URL</label><input name="login_panel_image_url" class="form-control" value="{{ old('login_panel_image_url', $settings['login_panel_image_url'] ?? '') }}" placeholder="https://... or uploaded image path"></div>
        <div class="col-md-4"><label class="form-label">Upload Panel Image</label><input type="file" name="login_panel_image_upload" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
        @if(! empty($settings['login_panel_image_url']))
            <div class="col-md-4">
                <label class="form-label">Current Panel Image</label>
                <div class="border rounded overflow-hidden" style="height: 88px; background: #f8fafc;">
                    <img src="{{ \Illuminate\Support\Str::startsWith($settings['login_panel_image_url'], ['http://', 'https://']) ? $settings['login_panel_image_url'] : asset(ltrim($settings['login_panel_image_url'], '/')) }}" alt="Current login panel image" style="width:100%; height:100%; object-fit:cover;">
                </div>
            </div>
        @endif
        <div class="col-md-4"><label class="form-label">Background Image URL</label><input name="login_background_url" class="form-control" value="{{ old('login_background_url', $settings['login_background_url'] ?? '') }}" placeholder="https://... or uploaded image path"></div>
        <div class="col-md-4"><label class="form-label">Upload Background Image</label><input type="file" name="login_background_upload" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
        @if(! empty($settings['login_background_url']))
            <div class="col-md-4">
                <label class="form-label">Current Background Image</label>
                <div class="border rounded overflow-hidden" style="height: 88px; background: #f8fafc;">
                    <img src="{{ \Illuminate\Support\Str::startsWith($settings['login_background_url'], ['http://', 'https://']) ? $settings['login_background_url'] : asset(ltrim($settings['login_background_url'], '/')) }}" alt="Current login background image" style="width:100%; height:100%; object-fit:cover;">
                </div>
            </div>
        @endif
        <div class="col-12"><label class="form-label">Intro Text</label><textarea name="login_intro_text" rows="2" class="form-control" placeholder="Manage packages, invoices, customers, manifests, rates, and delivery workflows.">{{ old('login_intro_text', $settings['login_intro_text'] ?? '') }}</textarea></div>
        <div class="col-md-3"><label class="form-label">Feature 1</label><input name="login_feature_1" class="form-control" value="{{ old('login_feature_1', $settings['login_feature_1'] ?? '') }}" placeholder="Express Delivery"></div>
        <div class="col-md-3"><label class="form-label">Feature 1 Icon</label><input name="login_feature_icon_1" class="form-control" value="{{ old('login_feature_icon_1', $settings['login_feature_icon_1'] ?? '') }}" placeholder="fa-truck-fast"></div>
        <div class="col-md-3"><label class="form-label">Feature 2</label><input name="login_feature_2" class="form-control" value="{{ old('login_feature_2', $settings['login_feature_2'] ?? '') }}" placeholder="Secure Shipping"></div>
        <div class="col-md-3"><label class="form-label">Feature 2 Icon</label><input name="login_feature_icon_2" class="form-control" value="{{ old('login_feature_icon_2', $settings['login_feature_icon_2'] ?? '') }}" placeholder="fa-shield"></div>
        <div class="col-md-3"><label class="form-label">Feature 3</label><input name="login_feature_3" class="form-control" value="{{ old('login_feature_3', $settings['login_feature_3'] ?? '') }}" placeholder="Real-time Tracking"></div>
        <div class="col-md-3"><label class="form-label">Feature 3 Icon</label><input name="login_feature_icon_3" class="form-control" value="{{ old('login_feature_icon_3', $settings['login_feature_icon_3'] ?? '') }}" placeholder="fa-location-dot"></div>
        <div class="col-12"><div class="text-muted small settings-help">These fields control the courier admin login page and the customer registration page on this courier's subdomain. Blank fields use the default text and images.</div></div>
    </div>
        </div>
    </details>

    <details class="settings-collapse settings-section" data-settings-section="addresses" open>
        <summary>Customer Mailing Address Templates</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>Address Templates</h2><p>Manage customer mailing address templates.</p></div>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <div>
            <div class="text-muted small">You can add multiple addresses for different shipping purposes.</div>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="addWarehouseAddress"><i class="fas fa-plus me-1"></i>Add Address</button>
    </div>

    <div id="warehouseAddresses" class="d-grid gap-3 mb-4">
        @foreach(old('warehouse_addresses', $warehouseAddresses) as $index => $address)
            @php
                $selectedCountry = $address['country'] ?? '';
                $selectedCode = strtoupper($address['country_code'] ?? ($countryOptions[$selectedCountry] ?? ''));
                $selectedFlag = $address['flag_icon'] ?? '';
                $selectedFlagImage = $address['flag_image'] ?? '';
                $selectedFlagImageUrl = $selectedFlagImage
                    ? (\Illuminate\Support\Str::startsWith($selectedFlagImage, ['http://', 'https://']) ? $selectedFlagImage : asset(ltrim($selectedFlagImage, '/')))
                    : null;
                $selectCountries = $selectedCountry && ! array_key_exists($selectedCountry, $countryOptions)
                    ? array_merge([$selectedCountry => $selectedCode], $countryOptions)
                    : $countryOptions;
            @endphp
            <div class="border rounded p-3 warehouse-address-item">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div class="fw-bold small text-uppercase text-muted">Address Template <span class="warehouse-address-number">{{ $index + 1 }}</span></div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-warehouse-address><i class="fas fa-trash"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label">Label</label><input name="warehouse_addresses[{{ $index }}][label]" class="form-control" value="{{ $address['label'] ?? '' }}" placeholder="USA Air"></div>
                    <div class="col-md-3">
                        <label class="form-label">Flag Icon</label>
                        <div class="d-flex align-items-center gap-2">
                            <div class="warehouse-flag-preview" data-warehouse-flag>
                                @if($selectedFlagImageUrl)
                                    <img src="{{ $selectedFlagImageUrl }}" alt="{{ $address['label'] ?? 'Warehouse' }} flag" data-warehouse-flag-image>
                                @else
                                    <span data-warehouse-flag-emoji>{{ $selectedFlag ?: $selectedCode }}</span>
                                @endif
                            </div>
                            <input type="file" name="warehouse_flag_uploads[{{ $index }}]" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp,image/gif" data-warehouse-flag-upload>
                        </div>
                        <input type="hidden" name="warehouse_addresses[{{ $index }}][flag_icon]" value="{{ $selectedFlag }}" data-warehouse-flag-input>
                        <input type="hidden" name="warehouse_addresses[{{ $index }}][flag_image]" value="{{ $selectedFlagImage }}">
                        <div class="form-text">Optional. Country flag is used when empty.</div>
                    </div>
                    <div class="col-md-2"><label class="form-label">Country Code</label><input name="warehouse_addresses[{{ $index }}][country_code]" class="form-control text-uppercase" value="{{ $selectedCode }}" placeholder="US" data-warehouse-country-code readonly></div>
                    <div class="col-md-2"><label class="form-label">Freight Type</label><input name="warehouse_addresses[{{ $index }}][freight_type]" class="form-control" value="{{ $address['freight_type'] ?? '' }}" placeholder="air"></div>
                    <div class="col-md-3"><label class="form-label">Country</label><select name="warehouse_addresses[{{ $index }}][country]" class="form-select" data-warehouse-country><option value="">Select country</option>@foreach($selectCountries as $country => $code)<option value="{{ $country }}" data-code="{{ $code }}" @selected($selectedCountry === $country)>{{ $country }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Name Suffix</label><input name="warehouse_addresses[{{ $index }}][name_suffix]" class="form-control" value="{{ $address['name_suffix'] ?? '' }}" placeholder="OLOG"></div>
                    <div class="col-md-5"><label class="form-label">Address Line 1</label><input name="warehouse_addresses[{{ $index }}][address_1]" class="form-control" value="{{ $address['address_1'] ?? '' }}"></div>
                    <div class="col-md-2"><label class="form-label">Address 2 Suffix</label><input name="warehouse_addresses[{{ $index }}][address_2_suffix]" class="form-control" value="{{ $address['address_2_suffix'] ?? '' }}" placeholder="SEA"></div>
                    <div class="col-md-2"><label class="form-label">Marks Suffix</label><input name="warehouse_addresses[{{ $index }}][marks_suffix]" class="form-control" value="{{ $address['marks_suffix'] ?? '' }}" placeholder="AIR"></div>
                    <div class="col-md-3"><label class="form-label">City</label><input name="warehouse_addresses[{{ $index }}][city]" class="form-control" value="{{ $address['city'] ?? '' }}"></div>
                    <div class="col-md-3"><label class="form-label">State</label><input name="warehouse_addresses[{{ $index }}][state]" class="form-control" value="{{ $address['state'] ?? '' }}"></div>
                    <div class="col-md-3"><label class="form-label">Province</label><input name="warehouse_addresses[{{ $index }}][province]" class="form-control" value="{{ $address['province'] ?? '' }}"></div>
                    <div class="col-md-3"><label class="form-label">Zip Code</label><input name="warehouse_addresses[{{ $index }}][zip_code]" class="form-control" value="{{ $address['zip_code'] ?? '' }}"></div>
                    <div class="col-md-4"><label class="form-label">Entrance No.</label><input name="warehouse_addresses[{{ $index }}][entrance_no]" class="form-control" value="{{ $address['entrance_no'] ?? '' }}"></div>
                    <div class="col-md-4"><label class="form-label">Number / Phone</label><input name="warehouse_addresses[{{ $index }}][number]" class="form-control" value="{{ $address['number'] ?? '' }}"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="hidden" name="warehouse_addresses[{{ $index }}][is_active]" value="0"><input class="form-check-input" type="checkbox" name="warehouse_addresses[{{ $index }}][is_active]" value="1" @checked($address['is_active'] ?? true)> <label class="form-check-label">Active</label></div></div>
                    <div class="col-md-6"><label class="form-label">Detailed Address</label><textarea name="warehouse_addresses[{{ $index }}][detailed_address]" rows="2" class="form-control">{{ $address['detailed_address'] ?? '' }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Note</label><textarea name="warehouse_addresses[{{ $index }}][note]" rows="2" class="form-control">{{ $address['note'] ?? '' }}</textarea></div>
                </div>
            </div>
        @endforeach
    </div>
        </div>
    </details>

    <details class="settings-collapse settings-section" data-settings-section="finance" open>
        <summary>Financial Automation</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>Finance</h2><p>Configure storage, processing, and invoice billing behavior.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Free Storage Days</label><input type="number" min="0" name="storage_free_days" class="form-control" value="{{ old('storage_free_days', $settings['storage_free_days'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Flat Storage Fee JMD</label><input type="number" step="0.01" min="0" name="storage_flat_fee_jmd" class="form-control" value="{{ old('storage_flat_fee_jmd', $settings['storage_flat_fee_jmd'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Daily Increment JMD</label><input type="number" step="0.01" min="0" name="storage_daily_increment_jmd" class="form-control" value="{{ old('storage_daily_increment_jmd', $settings['storage_daily_increment_jmd'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Processing Fee JMD</label><input type="number" step="0.01" min="0" name="processing_fee_jmd" class="form-control" value="{{ old('processing_fee_jmd', $settings['processing_fee_jmd'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Invoice Weight Billing</label><select name="billing_weight_mode" class="form-select"><option value="actual" @selected(old('billing_weight_mode', $settings['billing_weight_mode'] ?? 'actual') === 'actual')>Bill as-is</option><option value="round_up" @selected(old('billing_weight_mode', $settings['billing_weight_mode'] ?? 'actual') === 'round_up')>Round up</option><option value="round_down" @selected(old('billing_weight_mode', $settings['billing_weight_mode'] ?? 'actual') === 'round_down')>Round down</option></select></div>
    </div>
        </div>
    </details>

    <details class="settings-collapse settings-section" data-settings-section="loyalty" open>
        <summary>Earn & Loyalty</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>Loyalty</h2><p>Configure customer rewards and referral points.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check pb-2">
                <input type="hidden" name="loyalty_enabled" value="0">
                <input class="form-check-input" type="checkbox" name="loyalty_enabled" value="1" id="loyalty_enabled" @checked(old('loyalty_enabled', $settings['loyalty_enabled'] ?? 0))>
                <label class="form-check-label fw-bold" for="loyalty_enabled">Enable rewards</label>
            </div>
        </div>
        <div class="col-md-3"><label class="form-label">Signup Points</label><input type="number" step="0.01" min="0" name="loyalty_signup_points" class="form-control" value="{{ old('loyalty_signup_points', $settings['loyalty_signup_points'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Delivered Package Points</label><input type="number" step="0.01" min="0" name="loyalty_package_points" class="form-control" value="{{ old('loyalty_package_points', $settings['loyalty_package_points'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Referral Points</label><input type="number" step="0.01" min="0" name="loyalty_referral_points" class="form-control" value="{{ old('loyalty_referral_points', $settings['loyalty_referral_points'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Min Referral Points to Apply</label><input type="number" step="0.01" min="0" name="referral_points_minimum_to_apply" class="form-control" value="{{ old('referral_points_minimum_to_apply', $settings['referral_points_minimum_to_apply'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Invoice Points</label><input type="number" step="0.01" min="0" name="loyalty_invoice_points_per_amount" class="form-control" value="{{ old('loyalty_invoice_points_per_amount', $settings['loyalty_invoice_points_per_amount'] ?? 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Per JMD Amount</label><input type="number" step="0.01" min="0.01" name="loyalty_invoice_amount_step_jmd" class="form-control" value="{{ old('loyalty_invoice_amount_step_jmd', $settings['loyalty_invoice_amount_step_jmd'] ?? 1000) }}"></div>
        <div class="col-md-3"><label class="form-label">Point Value JMD</label><input type="number" step="0.01" min="0" name="loyalty_point_value_jmd" class="form-control" value="{{ old('loyalty_point_value_jmd', $settings['loyalty_point_value_jmd'] ?? 0) }}"></div>
        <div class="col-md-3 d-flex align-items-end"><div class="text-muted small pb-2">Customers see points, referral code, and recent earning activity on their portal dashboard.</div></div>
    </div>
        </div>
    </details>

    @if($canUsePaymentsModule)
    <details class="settings-collapse settings-section" data-settings-section="payments" open>
        <summary>Banking & Payment Gateways</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>Payments</h2><p>Manage banking details and payment gateway keys.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-md-12"><label class="form-label">Banking Details for Customer Portal</label><textarea name="banking_details" rows="4" class="form-control">{{ old('banking_details', $settings['banking_details'] ?? '') }}</textarea></div>
        <div class="col-md-4"><label class="form-label">Dime API Key</label><input name="dime_api_key" class="form-control" value="{{ old('dime_api_key', $settings['dime_api_key'] ?? '') }}"></div>
        <div class="col-md-4"><label class="form-label">PayPal Client ID</label><input name="paypal_client_id" class="form-control" value="{{ old('paypal_client_id', $settings['paypal_client_id'] ?? '') }}"></div>
        <div class="col-md-4"><label class="form-label">PayPal Secret</label><input name="paypal_secret" class="form-control" value="{{ old('paypal_secret', $settings['paypal_secret'] ?? '') }}"></div>
        <div class="col-md-4"><label class="form-label">Amber Pay API Key</label><input name="amber_pay_api_key" class="form-control" value="{{ old('amber_pay_api_key', $settings['amber_pay_api_key'] ?? '') }}"></div>
    </div>
        </div>
    </details>
    @endif

    <details class="settings-collapse settings-section" data-settings-section="email" open>
        <summary>Rich Email Footer</summary>
        <div class="settings-collapse-body">
    <div class="settings-section-head">
        <div><h2>Email Notifications</h2><p>Control automated customer email templates and branded footer content.</p></div>
    </div>
    <div class="alert alert-info mb-3">
        <strong>Template variables:</strong>
        <code>@{{ company_name }}</code>, <code>@{{ courier_name }}</code>, <code>@{{ portal_url }}</code>, <code>@{{ customer_name }}</code>, <code>@{{ customer_email }}</code>, <code>@{{ mailbox }}</code>, <code>@{{ tracking_number }}</code>, <code>@{{ hawb }}</code>, <code>@{{ supplier_tracking }}</code>, <code>@{{ merchant }}</code>, <code>@{{ status }}</code>, <code>@{{ weight }}</code>, <code>@{{ cost }}</code>, <code>@{{ note }}</code>, <code>@{{ invoice_number }}</code>, <code>@{{ invoice_total }}</code>, <code>@{{ payment_status }}</code>, <code>@{{ request_number }}</code>, <code>@{{ package_count }}</code>, <code>@{{ package_list }}</code>, <code>@{{ mailing_addresses }}</code>, <code>@{{ usa_air_address }}</code>, <code>@{{ usa_sea_address }}</code>, <code>@{{ china_air_address }}</code>, <code>@{{ china_sea_address }}</code>, <code>@{{ branch_name }}</code>, <code>@{{ branch_address }}</code>, <code>@{{ pickup_location }}</code>, <code>@{{ reset_url }}</code>, <code>@{{ expires_minutes }}</code>.
    </div>
    <div class="border rounded p-3 mb-4 settings-import-card">
        <div class="settings-section-head mb-3">
            <div><h2 class="h5">SMTP & From Email</h2><p>Use courier-specific SMTP settings for this portal. Leave fields blank to use System Admin defaults.</p></div>
        </div>
        @php
            $smtpStatus = $settings['smtp_status'] ?? (filled($settings['smtp_host'] ?? null) ? 'untested' : 'disabled');
            $smtpBadge = match ($smtpStatus) {
                'active' => 'bg-success',
                'failed' => 'bg-danger',
                'untested' => 'bg-warning text-dark',
                default => 'bg-secondary',
            };
        @endphp
        <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
            <span class="badge {{ $smtpBadge }}">SMTP {{ ucfirst($smtpStatus) }}</span>
            @if(filled($settings['smtp_last_tested_at'] ?? null))
                <small class="text-muted">Last tested: {{ $settings['smtp_last_tested_at'] }}</small>
            @endif
            @if(filled($settings['smtp_last_error'] ?? null))
                <small class="text-danger">{{ $settings['smtp_last_error'] }}</small>
            @endif
        </div>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">SMTP Host</label><input name="smtp_host" class="form-control" value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" placeholder="smtp.example.com"></div>
            <div class="col-md-2"><label class="form-label">Port</label><input type="number" min="1" max="65535" name="smtp_port" class="form-control" value="{{ old('smtp_port', $settings['smtp_port'] ?? '') }}" placeholder="587"></div>
            <div class="col-md-2"><label class="form-label">Encryption</label><select name="smtp_encryption" class="form-select"><option value="">Use default</option><option value="none" @selected(old('smtp_encryption', $settings['smtp_encryption'] ?? '') === 'none')>None</option><option value="tls" @selected(old('smtp_encryption', $settings['smtp_encryption'] ?? '') === 'tls')>TLS</option><option value="ssl" @selected(old('smtp_encryption', $settings['smtp_encryption'] ?? '') === 'ssl')>SSL</option></select></div>
            <div class="col-md-4"><label class="form-label">SMTP Username</label><input name="smtp_username" class="form-control" value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}" placeholder="noreply@example.com"></div>
            <div class="col-md-4"><label class="form-label">SMTP Password</label><input type="password" name="smtp_password" class="form-control" placeholder="{{ filled($settings['smtp_password'] ?? null) ? 'Leave blank to keep current password' : 'SMTP password' }}"></div>
            <div class="col-md-4"><label class="form-label">From Email</label><input type="email" name="mail_from_address" class="form-control" value="{{ old('mail_from_address', $settings['mail_from_address'] ?? '') }}" placeholder="noreply@example.com"></div>
            <div class="col-md-4"><label class="form-label">From Name</label><input name="mail_from_name" class="form-control" value="{{ old('mail_from_name', $settings['mail_from_name'] ?? '') }}" placeholder="{{ auth()->user()->tenant->company_name ?? 'Courier' }}"></div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" name="settings_action" value="test_smtp" class="btn btn-outline-primary w-100">
                    <i class="fas fa-paper-plane me-1"></i>Test & Save SMTP
                </button>
            </div>
        </div>
    </div>
    <div class="accordion mb-4" id="emailTemplateAccordion">
        @foreach($emailTemplates as $templateKey => $template)
            <div class="accordion-item">
                <h3 class="accordion-header" id="emailTemplateHeading{{ $loop->index }}">
                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#emailTemplate{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="emailTemplate{{ $loop->index }}">
                        {{ $template['label'] ?? ucwords(str_replace('_', ' ', $templateKey)) }}
                    </button>
                </h3>
                <div id="emailTemplate{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="emailTemplateHeading{{ $loop->index }}" data-bs-parent="#emailTemplateAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check pb-2">
                                    <input type="hidden" name="email_templates[{{ $templateKey }}][enabled]" value="0">
                                    <input class="form-check-input" type="checkbox" name="email_templates[{{ $templateKey }}][enabled]" value="1" id="emailTemplateEnabled{{ $loop->index }}" @checked($template['enabled'] ?? true)>
                                    <label class="form-check-label fw-bold" for="emailTemplateEnabled{{ $loop->index }}">Enabled</label>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">Subject</label>
                                <input name="email_templates[{{ $templateKey }}][subject]" class="form-control" value="{{ old('email_templates.'.$templateKey.'.subject', $template['subject'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Headline</label>
                                <input name="email_templates[{{ $templateKey }}][headline]" class="form-control" value="{{ old('email_templates.'.$templateKey.'.headline', $template['headline'] ?? '') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Button Label</label>
                                <input name="email_templates[{{ $templateKey }}][button_label]" class="form-control" value="{{ old('email_templates.'.$templateKey.'.button_label', $template['button_label'] ?? '') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Button URL</label>
                                <input name="email_templates[{{ $templateKey }}][button_url]" class="form-control" value="{{ old('email_templates.'.$templateKey.'.button_url', $template['button_url'] ?? '') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Body</label>
                                <div class="email-editor-toolbar btn-group-sm mb-2" role="group" aria-label="Template editor tools">
                                    <select class="form-select form-select-sm" data-rich-font>
                                        <option value="">Font</option>
                                        <option value="Arial, sans-serif">Arial</option>
                                        <option value="Georgia, serif">Georgia</option>
                                        <option value="Tahoma, sans-serif">Tahoma</option>
                                        <option value="Times New Roman, serif">Times New Roman</option>
                                        <option value="Verdana, sans-serif">Verdana</option>
                                    </select>
                                    <select class="form-select form-select-sm" data-rich-size>
                                        <option value="">Size</option>
                                        <option value="12px">12</option>
                                        <option value="14px">14</option>
                                        <option value="16px">16</option>
                                        <option value="18px">18</option>
                                        <option value="22px">22</option>
                                        <option value="28px">28</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="bold"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="italic"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="underline"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="justifyLeft"><i class="fas fa-align-left"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="justifyCenter"><i class="fas fa-align-center"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="justifyRight"><i class="fas fa-align-right"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-command="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-link><i class="fas fa-link"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-table><i class="fas fa-table"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-block="columns"><i class="fas fa-columns"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-rich-block="pagebreak"><i class="fas fa-file-lines"></i></button>
                                    <select class="form-select form-select-sm" style="width: 180px;" data-rich-token>
                                        <option value="">Insert variable</option>
                                        @foreach(['company_name','courier_name','portal_url','customer_name','customer_email','mailbox','tracking_number','hawb','supplier_tracking','merchant','status','weight','cost','note','invoice_number','invoice_total','payment_status','request_number','package_count','package_list','mailing_addresses','usa_air_address','usa_sea_address','china_air_address','china_sea_address','branch_name','branch_address','pickup_location','reset_url','expires_minutes'] as $token)
                                            <option value="&#123;&#123; {{ $token }} &#125;&#125;">&#123;&#123; {{ $token }} &#125;&#125;</option>
                                        @endforeach
                                    </select>
                                </div>
                                <textarea name="email_templates[{{ $templateKey }}][body]" rows="5" class="form-control d-none" data-rich-body>{{ old('email_templates.'.$templateKey.'.body', $template['body'] ?? '') }}</textarea>
                                <div class="form-control" contenteditable="true" data-rich-editor style="min-height: 150px; overflow:auto;">{!! old('email_templates.'.$templateKey.'.body', $template['body'] ?? '') !!}</div>
                                <div class="form-text">Supports formatted text, HTML/source content, tables, columns, rows, and page-break markers.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Upload Documents / Forms</label>
                                <input type="file" name="email_template_attachments[{{ $templateKey }}][]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp">
                                <div class="form-text">Attach onboarding forms, customs declaration forms, authorization forms, and other required documents.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Attachment URLs</label>
                                <textarea name="email_templates[{{ $templateKey }}][attachment_urls]" rows="2" class="form-control" placeholder="One downloadable link per line"></textarea>
                            </div>
                            @if(! empty($template['attachments']))
                                <div class="col-12">
                                    <label class="form-label">Saved Attachments</label>
                                    <div class="d-grid gap-1">
                                        @foreach($template['attachments'] as $attachmentIndex => $attachment)
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" checked name="email_templates[{{ $templateKey }}][existing_attachments][{{ $attachmentIndex }}][keep]" value="1">
                                                <input type="hidden" name="email_templates[{{ $templateKey }}][existing_attachments][{{ $attachmentIndex }}][name]" value="{{ $attachment['name'] ?? 'Attachment' }}">
                                                <input type="hidden" name="email_templates[{{ $templateKey }}][existing_attachments][{{ $attachmentIndex }}][url]" value="{{ $attachment['url'] ?? '' }}">
                                                <input type="hidden" name="email_templates[{{ $templateKey }}][existing_attachments][{{ $attachmentIndex }}][path]" value="{{ $attachment['path'] ?? '' }}">
                                                <span class="form-check-label">{{ $attachment['name'] ?? 'Attachment' }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="email-editor-toolbar btn-group-sm mb-2" role="group" aria-label="Email footer editor">
        <select class="form-select form-select-sm" data-footer-font>
            <option value="">Font</option>
            <option value="Arial, sans-serif">Arial</option>
            <option value="Georgia, serif">Georgia</option>
            <option value="Tahoma, sans-serif">Tahoma</option>
            <option value="Times New Roman, serif">Times New Roman</option>
            <option value="Verdana, sans-serif">Verdana</option>
        </select>
        <select class="form-select form-select-sm" data-footer-size>
            <option value="">Size</option>
            <option value="12px">12</option>
            <option value="14px">14</option>
            <option value="16px">16</option>
            <option value="18px">18</option>
            <option value="22px">22</option>
            <option value="28px">28</option>
        </select>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="bold"><i class="fas fa-bold"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="italic"><i class="fas fa-italic"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="underline"><i class="fas fa-underline"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="justifyLeft"><i class="fas fa-align-left"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="justifyCenter"><i class="fas fa-align-center"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="justifyRight"><i class="fas fa-align-right"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-command="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-footer-table><i class="fas fa-table"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-link><i class="fas fa-link"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-editor-image><i class="fas fa-image"></i></button>
    </div>
    <textarea name="email_footer_html" id="email_footer_html" class="d-none">{{ old('email_footer_html', $settings['email_footer_html'] ?? '') }}</textarea>
    <div id="email_footer_editor" class="form-control mb-3" contenteditable="true" style="min-height: 140px; overflow:auto;">{!! old('email_footer_html', $settings['email_footer_html'] ?? '') !!}</div>
    <div class="mb-3"><label class="form-label">Footer Text</label><textarea name="email_footer_text" rows="2" class="form-control" placeholder="Plain footer text for customer emails.">{{ old('email_footer_text', $settings['email_footer_text'] ?? '') }}</textarea></div>
    <div class="mb-3"><label class="form-label">Default Attachment URL</label><input name="email_attachment_url" class="form-control" value="{{ old('email_attachment_url', $settings['email_attachment_url'] ?? '') }}" placeholder="https://..."></div>
    <div class="mb-3">
        <input type="hidden" name="sound_prompts_enabled" value="0">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="sound_prompts_enabled" value="1" @checked((string) old('sound_prompts_enabled', $settings['sound_prompts_enabled'] ?? '1') !== '0')>
            <span class="form-check-label fw-bold">Enable sound prompts</span>
        </label>
    </div>
    <div class="text-muted small mb-3">Use the toolbar for formatted footer content, or add plain footer text. The attachment URL is added to customer emails as a downloadable link.</div>
        </div>
    </details>

    <div class="settings-save-bar"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Settings</button></div>
</form>

<div class="panel p-4 mb-4 settings-section" data-settings-section="support-access">
    <div class="settings-section-head">
        <div>
            <h2>Courier-Generated One-Time Support Code</h2>
            <p>Generate limited, one-time access for System Admin or support staff. You control the permissions and can revoke access at any time.</p>
        </div>
    </div>
    @if(session('support_access_code'))
        <div class="alert alert-info"><strong>Share this code once:</strong> {{ session('support_access_code') }}</div>
    @endif
    <form method="POST" action="{{ route('courier.settings.support-access-codes.store') }}" class="row g-3 mb-4">
        @csrf
        <div class="col-md-4"><label class="form-label">Label</label><input name="label" class="form-control" placeholder="Support ticket, issue, or technician"></div>
        <div class="col-md-3"><label class="form-label">Expires In</label><select name="expires_in_minutes" class="form-select"><option value="30">30 minutes</option><option value="60">1 hour</option><option value="240">4 hours</option><option value="1440">1 day</option><option value="10080">7 days</option></select></div>
        <div class="col-12">
            <label class="form-label">Permission level</label>
            <div class="row g-2">
                @foreach($supportPermissions as $permission)
                    <div class="col-md-4">
                        <label class="form-check">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" class="form-check-input">
                            <span class="form-check-label">{{ $permission->module }} / {{ $permission->display_name }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-12"><button class="btn btn-primary"><i class="fas fa-key me-1"></i>Generate One-Time Code</button></div>
    </form>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Label</th><th>Permissions</th><th>Expires</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($supportCodes as $code)
                <tr>
                    <td>{{ $code->label ?: 'Support code' }}</td>
                    <td>{{ count($code->permissions ?: []) }} permission(s)</td>
                    <td>{{ optional($code->expires_at)->format('M d, Y h:i A') ?: 'No expiry' }}</td>
                    <td>{{ $code->revoked_at ? 'Revoked' : ($code->used_at ? 'Used' : ($code->isUsable() ? 'Active' : 'Expired')) }}</td>
                    <td class="text-end">
                        @if(! $code->revoked_at)
                            <form method="POST" action="{{ route('courier.settings.support-access-codes.revoke', $code) }}" data-confirm="Revoke this support access code? Active support users will lose authorization.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Revoke</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No support access codes generated yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel p-4 mb-4 settings-section" data-settings-section="maintenance">
    <details class="settings-collapse" open>
        <summary>Data Backup, Restore & Sync</summary>
        <div class="settings-collapse-body">
            <div class="settings-section-head">
                <div><h2>Backup & Restore</h2><p>Backup, restore, sync, or clear courier testing data.</p></div>
            </div>
            <div class="settings-maintenance-grid">
                <div class="settings-action-card">
                    <a href="{{ route('courier.maintenance.backup') }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Backup Courier</a>
                    <div class="form-text">Downloads a JSON snapshot of this courier portal.</div>
                </div>
                <div class="settings-action-card">
                    <form method="POST" action="{{ route('courier.maintenance.automatic-backup') }}">
                        @csrf
                        <select name="frequency" class="form-select mb-2">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                        <button class="btn btn-outline-primary"><i class="fas fa-clock-rotate-left me-1"></i>Run Automatic Backup</button>
                    </form>
                    <div class="form-text">Runs the scheduled backup engine immediately.</div>
                </div>
                <div class="settings-action-card">
                    <form method="POST" action="{{ route('courier.maintenance.restore') }}" enctype="multipart/form-data" data-confirm="Restore this courier backup? Current courier snapshot data will be replaced.">
                        @csrf
                        <input type="file" name="backup_file" class="form-control mb-2" accept=".json,.txt" required>
                        <button class="btn btn-outline-secondary"><i class="fas fa-upload me-1"></i>Restore Backup</button>
                    </form>
                </div>
                <div class="settings-action-card">
                    <form method="POST" action="{{ route('courier.maintenance.sync') }}">
                        @csrf
                        <button class="btn btn-outline-primary"><i class="fas fa-rotate me-1"></i>Sync Defaults</button>
                    </form>
                    <div class="form-text">Rebuilds default statuses, package types, loyalty tiers, and customer addresses.</div>
                </div>
                <div class="settings-action-card">
                    <form method="POST" action="{{ route('courier.maintenance.clear') }}" data-confirm="Clear courier testing data? Staff, settings, branches, statuses, and rates are kept.">
                        @csrf
                        <input name="confirm_text" class="form-control mb-2" placeholder="Type FRESH START" required>
                        <button class="btn btn-outline-danger"><i class="fas fa-broom me-1"></i>Clear Test Data</button>
                    </form>
                </div>
            </div>
        </div>
    </details>
</div>

<div class="settings-standalone-sections">
    <div class="settings-section" data-settings-section="tiers">
        <div class="panel p-4">
            <details class="settings-collapse" open>
                <summary>Pricing & Loyalty Tiers</summary>
                <div class="settings-collapse-body">
                    <div class="settings-section-head">
                        <div><h2>Pricing Tiers</h2><p>Manage loyalty groups, package thresholds, discounts, and tier-specific rate uploads.</p></div>
                    </div>
                    <div class="settings-tier-toolbar">
                        <span class="settings-tier-count"><i class="fas fa-layer-group"></i>{{ $tiers->count() }} tiers</span>
                        <a href="{{ route('courier.settings.tiers.rates.template') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>Rates Template</a>
                    </div>
                    <div class="settings-tier-list">
                    @forelse($tiers as $tier)
                        <div class="settings-tier-card">
                            <form id="tierUpdate{{ $tier->id }}" method="POST" action="{{ route('courier.settings.tiers.update', $tier) }}" class="d-none">@csrf @method('PUT')</form>
                            <div class="settings-tier-name">
                                <label class="form-label">Tier Name</label>
                                <input name="name" form="tierUpdate{{ $tier->id }}" class="form-control form-control-sm" value="{{ $tier->name }}" required>
                                <div class="settings-tier-checks">
                                    <label class="form-check small mb-0"><input class="form-check-input" form="tierUpdate{{ $tier->id }}" type="checkbox" name="is_default" value="1" @checked($tier->is_default)> Default</label>
                                    <label class="form-check small mb-0"><input class="form-check-input" form="tierUpdate{{ $tier->id }}" type="checkbox" name="is_active" value="1" @checked($tier->is_active)> Active</label>
                                    <span class="badge {{ $tier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $tier->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Min Packages</label>
                                <input type="number" name="min_packages_collected" form="tierUpdate{{ $tier->id }}" class="form-control form-control-sm" value="{{ $tier->min_packages_collected }}" min="0" required>
                            </div>
                            <div>
                                <label class="form-label">Discount %</label>
                                <input type="number" step="0.01" name="discount_percent" form="tierUpdate{{ $tier->id }}" class="form-control form-control-sm" value="{{ $tier->discount_percent }}" min="0" max="100">
                            </div>
                            <div class="settings-tier-actions">
                                <div class="settings-tier-buttons">
                                    <button form="tierUpdate{{ $tier->id }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i>Save</button>
                                    @if(! $tier->is_default)
                                        <form method="POST" action="{{ route('courier.settings.tiers.destroy', $tier) }}" data-confirm="Delete this loyalty tier and its rates?">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                                        </form>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('courier.settings.tiers.rates.import', $tier) }}" enctype="multipart/form-data" class="settings-tier-import">@csrf
                                    <input type="file" name="rate_file" class="form-control form-control-sm" accept=".csv">
                                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-arrow-up me-1"></i>Import Rates</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="border rounded p-3 text-muted">No pricing tiers have been created yet.</div>
                    @endforelse
                    </div>
                    <form method="POST" action="{{ route('courier.settings.tiers.store') }}" class="settings-tier-add">@csrf
                        <div class="fw-bold mb-2">Add New Tier</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-4"><label class="form-label">Tier Name</label><input name="name" class="form-control" placeholder="Gold" required></div>
                            <div class="col-lg-3"><label class="form-label">Min Packages</label><input type="number" name="min_packages_collected" class="form-control" placeholder="25" min="0" required></div>
                            <div class="col-lg-3"><label class="form-label">Discount %</label><input type="number" step="0.01" name="discount_percent" class="form-control" placeholder="5.00" min="0" max="100"></div>
                            <div class="col-lg-2"><button class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Add Tier</button></div>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </div>
    <div class="settings-section settings-alerts-section" data-settings-section="alerts">
        <div class="panel p-4">
            <details class="settings-collapse" open>
                <summary>Login Pop-Up Alerts</summary>
                <div class="settings-collapse-body">
                    <div class="settings-section-head">
                        <div>
                            <h2>Login Alerts</h2>
                            <p>Create, edit, schedule, and remove courier/customer login notifications.</p>
                        </div>
                    </div>

                    <div class="settings-alert-layout">
                        <div>
                            <form method="POST" action="{{ route('courier.settings.notifications.store') }}" class="settings-alert-box">
                                @csrf
                                <div class="fw-bold mb-3">Create New Alert</div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Title</label>
                                        <input name="title" class="form-control" placeholder="Service update" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Priority</label>
                                        <select name="priority" class="form-select">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Audience</label>
                                        <select name="audience" class="form-select">
                                            <option value="all">All</option>
                                            <option value="courier">Courier staff</option>
                                            <option value="customer">Customers</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Message</label>
                                        <textarea name="message" rows="5" class="form-control" placeholder="Write the login pop-up message..." required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Starts</label>
                                        <input type="datetime-local" name="starts_at" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ends</label>
                                        <input type="datetime-local" name="ends_at" class="form-control">
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                                        <label class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                            <span class="form-check-label">Active</span>
                                        </label>
                                        <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Alert</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="fw-bold">Existing Alerts</div>
                                <span class="text-muted small">{{ $notifications->total() }} total</span>
                            </div>

                            @forelse($notifications as $notification)
                                @php
                                    $priorityClass = $notification->priority === 'high' ? 'danger' : ($notification->priority === 'medium' ? 'warning text-dark' : 'secondary');
                                @endphp
                                <details class="settings-alert-card">
                                    <summary class="d-flex justify-content-between align-items-center gap-3">
                                        <span>
                                            <strong>{{ $notification->title }}</strong>
                                            <span class="badge bg-{{ $priorityClass }} ms-2">{{ ucfirst($notification->priority) }}</span>
                                            <span class="badge {{ $notification->is_active ? 'bg-success' : 'bg-secondary' }} ms-1">{{ $notification->is_active ? 'Active' : 'Inactive' }}</span>
                                        </span>
                                        <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $notification->audience)) }} | {{ optional($notification->created_at)->format('M d, Y') }}</small>
                                    </summary>
                                    <form method="POST" action="{{ route('courier.settings.notifications.update', $notification) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                            <div>
                                                <div class="fw-bold settings-alert-title">{{ $notification->title }}</div>
                                                <div class="d-flex flex-wrap gap-2 mt-1">
                                                    <span class="badge bg-{{ $priorityClass }}">{{ ucfirst($notification->priority) }}</span>
                                                    <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $notification->audience)) }}</span>
                                                    <span class="badge {{ $notification->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $notification->is_active ? 'Active' : 'Inactive' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Title</label>
                                                <input name="title" class="form-control" value="{{ old('title', $notification->title) }}" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Priority</label>
                                                <select name="priority" class="form-select">
                                                    @foreach(['low', 'medium', 'high'] as $priority)
                                                        <option value="{{ $priority }}" @selected($notification->priority === $priority)>{{ ucfirst($priority) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Audience</label>
                                                <select name="audience" class="form-select">
                                                    <option value="all" @selected($notification->audience === 'all')>All</option>
                                                    <option value="courier" @selected($notification->audience === 'courier')>Courier staff</option>
                                                    <option value="customer" @selected($notification->audience === 'customer')>Customers</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Message</label>
                                                <textarea name="message" rows="3" class="form-control" required>{{ old('message', $notification->message) }}</textarea>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Starts</label>
                                                <input type="datetime-local" name="starts_at" class="form-control" value="{{ optional($notification->starts_at)->format('Y-m-d\TH:i') }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Ends</label>
                                                <input type="datetime-local" name="ends_at" class="form-control" value="{{ optional($notification->ends_at)->format('Y-m-d\TH:i') }}">
                                            </div>
                                            <div class="col-12 settings-alert-actions">
                                                <label class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($notification->is_active)>
                                                    <span class="form-check-label">Active</span>
                                                </label>
                                                <button class="btn btn-outline-primary"><i class="fas fa-floppy-disk me-1"></i>Save</button>
                                            </div>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('courier.settings.notifications.destroy', $notification) }}" class="text-end mt-2" data-confirm="Delete this login alert?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </form>
                                </details>
                            @empty
                                <div class="border rounded p-4 text-center text-muted">No login alerts yet.</div>
                            @endforelse

                            <div class="mt-3">{{ $notifications->links('pagination::bootstrap-5') }}</div>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </div>
</div>

<div class="panel p-4 mt-4 settings-section" data-settings-section="import-export">
    <details class="settings-collapse" open>
        <summary>Export / Import Controls</summary>
        <div class="settings-collapse-body">
            <div class="settings-section-head">
                <div><h2>Import / Export</h2><p>Download templates, upload CSV data, and export operational records from one place.</p></div>
            </div>

            <div class="settings-exchange-section pt-0 mt-0 border-0">
                <div class="settings-exchange-section-title">CSV Imports</div>
                <div class="settings-exchange-grid">
                    @foreach([
                        'packages' => [
                            'label' => 'Packages',
                            'icon' => 'fas fa-box',
                            'description' => 'Upload package records with tracking, mailbox, customer, status, freight, billing, and received date fields.',
                            'button' => 'Import Packages',
                        ],
                        'users' => [
                            'label' => 'Users & Customers',
                            'icon' => 'fas fa-users',
                            'description' => 'Upload customer, staff, or admin accounts. Customer rows can include mailbox and address details.',
                            'button' => 'Import Users',
                        ],
                        'weight_rates' => [
                            'label' => 'Weight Rates',
                            'icon' => 'fas fa-scale-balanced',
                            'description' => 'Upload freight weight brackets with standard and express JMD prices.',
                            'button' => 'Import Rates',
                        ],
                    ] as $table => $meta)
                        <form method="POST" action="{{ route('courier.data.import', $table) }}" enctype="multipart/form-data" class="settings-exchange-card">
                            @csrf
                            <div>
                                <h3><i class="{{ $meta['icon'] }} me-1 text-primary"></i>{{ $meta['label'] }}</h3>
                                <p>{{ $meta['description'] }}</p>
                            </div>
                            <div class="settings-exchange-import">
                                <input type="file" name="file" class="form-control form-control-sm" accept=".csv" required>
                                <div class="settings-exchange-actions">
                                    <a href="{{ route('courier.data.template', $table) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>Template</a>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-file-arrow-up me-1"></i>{{ $meta['button'] }}</button>
                                </div>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="settings-exchange-section">
                <div class="settings-exchange-section-title">CSV Exports</div>
                <div class="settings-exchange-grid">
                    @foreach([
                        'customers' => ['label' => 'Customers', 'icon' => 'fas fa-address-book'],
                        'users' => ['label' => 'Users', 'icon' => 'fas fa-user-gear'],
                        'packages' => ['label' => 'Packages', 'icon' => 'fas fa-boxes-stacked'],
                        'invoices' => ['label' => 'Invoices', 'icon' => 'fas fa-file-invoice-dollar'],
                        'weight_rates' => ['label' => 'Weight Rates', 'icon' => 'fas fa-scale-balanced'],
                        'package_statuses' => ['label' => 'Package Statuses', 'icon' => 'fas fa-list-check'],
                    ] as $table => $meta)
                        <div class="settings-exchange-card">
                            <div>
                                <h3><i class="{{ $meta['icon'] }} me-1 text-primary"></i>{{ $meta['label'] }}</h3>
                                <p>Download current {{ strtolower($meta['label']) }} records as a CSV file.</p>
                            </div>
                            <div class="settings-exchange-actions">
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('courier.data.export', $table) }}"><i class="fas fa-download me-1"></i>Export CSV</a>
                                @if(in_array($table, ['packages', 'users', 'weight_rates'], true))
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('courier.data.template', $table) }}"><i class="fas fa-table me-1"></i>Template</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </details>
</div>

<div class="panel p-4 mt-4 settings-section" data-settings-section="mass-delete">
    <details class="settings-collapse">
        <summary>Mass Delete Controls</summary>
        <div class="settings-collapse-body">
            <div class="settings-section-head">
                <div><h2>Mass Delete</h2><p>Remove selected or full-module data after testing.</p></div>
                <form method="POST" action="{{ route('courier.settings.personal-security-code') }}">
                    @csrf
                    <button class="btn btn-outline-primary"><i class="fas fa-key me-1"></i>Generate / Rotate Personal Code</button>
                </form>
            </div>
            <form method="POST" action="{{ route('courier.settings.personal-security-code.update') }}" class="border rounded p-3 mb-3">
                @csrf
                @method('PUT')
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Set reusable destructive code</label>
                        <input type="password" name="security_code" class="form-control @error('security_code') is-invalid @enderror" minlength="6" maxlength="64" autocomplete="new-password" required>
                        @error('security_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Confirm destructive code</label>
                        <input type="password" name="security_code_confirmation" class="form-control" minlength="6" maxlength="64" autocomplete="new-password" required>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="fas fa-shield me-1"></i>Set Code</button>
                    </div>
                </div>
                <div class="form-text mt-2">
                    Letters, numbers, and hyphens only. This code remains valid for delete and other destructive actions until changed.
                    Current mode: <strong>{{ auth()->user()->security_code_reusable ? 'Reusable custom code' : 'Single-use generated code' }}</strong>.
                </div>
            </form>
            <div class="row g-3">
                @foreach(['packages' => 'Packages', 'invoices' => 'Invoices', 'weight_rates' => 'Weight Rates', 'package_statuses' => 'Package Statuses'] as $table => $label)
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('courier.data.mass-delete', $table) }}" class="border rounded p-3 h-100 settings-import-card" data-confirm="Mass delete {{ strtolower($label) }}? This cannot be undone.">
                            @csrf @method('DELETE')
                            <label class="form-label">Delete {{ $label }}</label>
                            <select name="delete_scope" class="form-select mb-2">
                                <option value="selected">Selected IDs only</option>
                                <option value="all">All {{ $label }}</option>
                            </select>
                            <textarea name="ids" rows="2" class="form-control mb-2" placeholder="IDs separated by commas or new lines"></textarea>
                            <input name="confirm_text" class="form-control mb-2" placeholder="Type DELETE to confirm">
                            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Mass Delete</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </details>
</div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const settingsButtons = document.querySelectorAll('[data-settings-tab]');
    const settingsSections = document.querySelectorAll('[data-settings-section]');
    const settingsFormPanel = document.getElementById('courierSettingsFormPanel');
    const formTabs = ['warehouse', 'login', 'addresses', 'finance', 'loyalty', @if($canUsePaymentsModule) 'payments', @endif 'email'];

    function activateSettingsSection(tab) {
        settingsButtons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.settingsTab === tab);
        });
        settingsSections.forEach(function (section) {
            section.classList.toggle('active', section.dataset.settingsSection === tab);
        });
        if (settingsFormPanel) {
            settingsFormPanel.style.display = formTabs.includes(tab) ? '' : 'none';
        }
    }

    settingsButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activateSettingsSection(button.dataset.settingsTab);
        });
    });

    const warehouseCountries = @json($countryOptions);
    const warehouseWrap = document.getElementById('warehouseAddresses');
    const addWarehouseBtn = document.getElementById('addWarehouseAddress');

    function flagFromCountryCode(code) {
        code = String(code || '').trim().toUpperCase();
        if (!/^[A-Z]{2}$/.test(code)) return code || '-';

        return Array.from(code).map(char => String.fromCodePoint(127397 + char.charCodeAt(0))).join('');
    }

    function countryOptionsHtml(selectedCountry = '') {
        const options = ['<option value="">Select country</option>'];
        Object.entries(warehouseCountries).forEach(function ([country, code]) {
            options.push(`<option value="${country}" data-code="${code}"${country === selectedCountry ? ' selected' : ''}>${country}</option>`);
        });

        return options.join('');
    }

    function refreshWarehouseCountry(item) {
        const country = item.querySelector('[data-warehouse-country]');
        const code = item.querySelector('[data-warehouse-country-code]');
        const flag = item.querySelector('[data-warehouse-flag]');
        const flagInput = item.querySelector('[data-warehouse-flag-input]');
        if (!country || !code || !flag || !flagInput) return;

        const selected = country.options[country.selectedIndex];
        const countryCode = selected?.dataset.code || '';
        const flagIcon = flagFromCountryCode(countryCode);
        code.value = countryCode;
        if (!flag.querySelector('img')) {
            const emoji = flag.querySelector('[data-warehouse-flag-emoji]');
            if (emoji) {
                emoji.textContent = flagIcon;
            } else {
                flag.textContent = flagIcon;
            }
        }
        flagInput.value = /^[A-Z]{2}$/.test(countryCode) ? flagIcon : '';
    }

    function previewWarehouseFlag(input) {
        const file = input.files && input.files[0];
        const item = input.closest('.warehouse-address-item');
        const preview = item?.querySelector('[data-warehouse-flag]');
        if (!file || !preview) return;

        const reader = new FileReader();
        reader.addEventListener('load', function () {
            preview.innerHTML = `<img src="${reader.result}" alt="Uploaded flag preview" data-warehouse-flag-image>`;
        });
        reader.readAsDataURL(file);
    }

    function refreshWarehouseIndexes() {
        if (!warehouseWrap) return;
        warehouseWrap.querySelectorAll('.warehouse-address-item').forEach(function (item, index) {
            const number = item.querySelector('.warehouse-address-number');
            if (number) number.textContent = index + 1;
            item.querySelectorAll('[name^="warehouse_addresses["]').forEach(function (field) {
                field.name = field.name.replace(/warehouse_addresses\[\d+\]/, 'warehouse_addresses[' + index + ']');
            });
        });
    }

    if (warehouseWrap) {
        warehouseWrap.querySelectorAll('.warehouse-address-item').forEach(refreshWarehouseCountry);
        warehouseWrap.addEventListener('change', function (event) {
            if (event.target.matches('[data-warehouse-country]')) {
                refreshWarehouseCountry(event.target.closest('.warehouse-address-item'));
            }
            if (event.target.matches('[data-warehouse-flag-upload]')) {
                previewWarehouseFlag(event.target);
            }
        });
        warehouseWrap.addEventListener('click', function (event) {
            const removeButton = event.target.closest('[data-remove-warehouse-address]');
            if (!removeButton) return;
            removeButton.closest('.warehouse-address-item')?.remove();
            refreshWarehouseIndexes();
        });
    }

    if (addWarehouseBtn && warehouseWrap) {
        addWarehouseBtn.addEventListener('click', function () {
            const index = warehouseWrap.querySelectorAll('.warehouse-address-item').length;
            const item = document.createElement('div');
            item.className = 'border rounded p-3 warehouse-address-item';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div class="fw-bold small text-uppercase text-muted">Address Template <span class="warehouse-address-number">${index + 1}</span></div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-warehouse-address><i class="fas fa-trash"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-3"><label class="form-label">Label</label><input name="warehouse_addresses[${index}][label]" class="form-control" placeholder="USA Air"></div>
                    <div class="col-md-3"><label class="form-label">Flag Icon</label><div class="d-flex align-items-center gap-2"><div class="warehouse-flag-preview" data-warehouse-flag><span data-warehouse-flag-emoji>-</span></div><input type="file" name="warehouse_flag_uploads[${index}]" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp,image/gif" data-warehouse-flag-upload></div><input type="hidden" name="warehouse_addresses[${index}][flag_icon]" data-warehouse-flag-input><input type="hidden" name="warehouse_addresses[${index}][flag_image]" value=""><div class="form-text">Optional. Country flag is used when empty.</div></div>
                    <div class="col-md-2"><label class="form-label">Country Code</label><input name="warehouse_addresses[${index}][country_code]" class="form-control text-uppercase" placeholder="US" data-warehouse-country-code readonly></div>
                    <div class="col-md-2"><label class="form-label">Freight Type</label><input name="warehouse_addresses[${index}][freight_type]" class="form-control" placeholder="air"></div>
                    <div class="col-md-3"><label class="form-label">Country</label><select name="warehouse_addresses[${index}][country]" class="form-select" data-warehouse-country>${countryOptionsHtml()}</select></div>
                    <div class="col-md-3"><label class="form-label">Name Suffix</label><input name="warehouse_addresses[${index}][name_suffix]" class="form-control" placeholder="OLOG"></div>
                    <div class="col-md-5"><label class="form-label">Address Line 1</label><input name="warehouse_addresses[${index}][address_1]" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label">Address 2 Suffix</label><input name="warehouse_addresses[${index}][address_2_suffix]" class="form-control" placeholder="SEA"></div>
                    <div class="col-md-2"><label class="form-label">Marks Suffix</label><input name="warehouse_addresses[${index}][marks_suffix]" class="form-control" placeholder="AIR"></div>
                    <div class="col-md-3"><label class="form-label">City</label><input name="warehouse_addresses[${index}][city]" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">State</label><input name="warehouse_addresses[${index}][state]" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Province</label><input name="warehouse_addresses[${index}][province]" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Zip Code</label><input name="warehouse_addresses[${index}][zip_code]" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Entrance No.</label><input name="warehouse_addresses[${index}][entrance_no]" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Number / Phone</label><input name="warehouse_addresses[${index}][number]" class="form-control"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="hidden" name="warehouse_addresses[${index}][is_active]" value="0"><input class="form-check-input" type="checkbox" name="warehouse_addresses[${index}][is_active]" value="1" checked> <label class="form-check-label">Active</label></div></div>
                    <div class="col-md-6"><label class="form-label">Detailed Address</label><textarea name="warehouse_addresses[${index}][detailed_address]" rows="2" class="form-control"></textarea></div>
                    <div class="col-md-6"><label class="form-label">Note</label><textarea name="warehouse_addresses[${index}][note]" rows="2" class="form-control"></textarea></div>
                </div>
            `;
            warehouseWrap.appendChild(item);
            refreshWarehouseCountry(item);
        });
    }

    const editor = document.getElementById('email_footer_editor');
    const field = document.getElementById('email_footer_html');

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    const savedEditorRanges = new WeakMap();

    function selectionBelongsTo(target) {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount) return false;
        const range = selection.getRangeAt(0);
        return target.contains(range.commonAncestorContainer);
    }

    function rememberEditorSelection(target) {
        if (!selectionBelongsTo(target)) return;
        savedEditorRanges.set(target, window.getSelection().getRangeAt(0).cloneRange());
    }

    function restoreEditorSelection(target) {
        const range = savedEditorRanges.get(target);
        if (!range) return;
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function trackEditorSelection(target) {
        ['keyup', 'mouseup', 'input', 'focus'].forEach(function (eventName) {
            target.addEventListener(eventName, function () {
                rememberEditorSelection(target);
            });
        });
    }

    function buildEditorTable(rows, columns) {
        rows = Math.max(1, Math.min(parseInt(rows || '2', 10) || 2, 20));
        columns = Math.max(1, Math.min(parseInt(columns || '2', 10) || 2, 10));
        let html = '<table style="width:100%; border-collapse:collapse; table-layout:fixed;"><tbody>';
        for (let row = 0; row < rows; row++) {
            html += '<tr>';
            for (let column = 0; column < columns; column++) {
                html += '<td style="border:1px solid #cbd5e1; padding:8px; vertical-align:top;">' + (row === 0 ? (column === 0 ? 'Label' : 'Value') : '&nbsp;') + '</td>';
            }
            html += '</tr>';
        }
        return html + '</tbody></table><p><br></p>';
    }

    function insertEditorHtml(target, html, sync) {
        target.focus();
        restoreEditorSelection(target);
        document.execCommand('insertHTML', false, html);
        rememberEditorSelection(target);
        sync();
    }

    function applyInlineStyle(target, styles, sync) {
        target.focus();
        restoreEditorSelection(target);
        const selection = window.getSelection();
        const text = selection && selection.rangeCount ? selection.toString() : '';
        const style = Object.entries(styles).filter(([, value]) => value).map(([key, value]) => `${key}:${value}`).join(';');
        if (!style) return;
        insertEditorHtml(target, `<span style="${style}">${escapeHtml(text || 'Text')}</span>`, sync);
    }

    function promptAndInsertTable(target, sync) {
        const rows = prompt('Rows', '2');
        if (rows === null) return;
        const columns = prompt('Columns', '2');
        if (columns === null) return;
        insertEditorHtml(target, buildEditorTable(rows, columns), sync);
    }

    document.querySelectorAll('[data-rich-editor]').forEach(function (richEditor) {
        const bodyField = richEditor.previousElementSibling;
        const templatePanel = richEditor.closest('.accordion-body');
        trackEditorSelection(richEditor);

        function syncRich() {
            if (bodyField) bodyField.value = richEditor.innerHTML.trim();
        }

        templatePanel?.querySelectorAll('[data-rich-command]').forEach(function (button) {
            button.addEventListener('click', function () {
                richEditor.focus();
                document.execCommand(button.dataset.richCommand, false, null);
                syncRich();
            });
        });

        templatePanel?.querySelector('[data-rich-font]')?.addEventListener('change', function (event) {
            applyInlineStyle(richEditor, {'font-family': event.target.value}, syncRich);
            event.target.value = '';
        });

        templatePanel?.querySelector('[data-rich-size]')?.addEventListener('change', function (event) {
            applyInlineStyle(richEditor, {'font-size': event.target.value}, syncRich);
            event.target.value = '';
        });

        templatePanel?.querySelector('[data-rich-link]')?.addEventListener('click', function () {
            const url = prompt('Enter link URL');
            if (!url) return;
            richEditor.focus();
            document.execCommand('createLink', false, url);
            syncRich();
        });

        templatePanel?.querySelector('[data-rich-table]')?.addEventListener('click', function () {
            promptAndInsertTable(richEditor, syncRich);
        });

        templatePanel?.querySelectorAll('[data-rich-block]').forEach(function (button) {
            button.addEventListener('click', function () {
                richEditor.focus();
                const block = button.dataset.richBlock === 'pagebreak'
                    ? '<hr style="page-break-after:always;">'
                    : '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;"><div>Column one</div><div>Column two</div></div>';
                insertEditorHtml(richEditor, block, syncRich);
            });
        });

        templatePanel?.querySelector('[data-rich-token]')?.addEventListener('change', function (event) {
            if (!event.target.value) return;
            richEditor.focus();
            document.execCommand('insertText', false, event.target.value);
            event.target.value = '';
            syncRich();
        });

        richEditor.addEventListener('input', syncRich);
        richEditor.closest('form')?.addEventListener('submit', syncRich);
        syncRich();
    });

    if (!editor || !field) return;
    trackEditorSelection(editor);

    function syncFooter() {
        field.value = editor.innerHTML.trim();
    }

    document.querySelectorAll('[data-editor-command]').forEach(function (button) {
        button.addEventListener('click', function () {
            editor.focus();
            document.execCommand(button.dataset.editorCommand, false, null);
            syncFooter();
        });
    });

    document.querySelector('[data-footer-font]')?.addEventListener('change', function (event) {
        applyInlineStyle(editor, {'font-family': event.target.value}, syncFooter);
        event.target.value = '';
    });

    document.querySelector('[data-footer-size]')?.addEventListener('change', function (event) {
        applyInlineStyle(editor, {'font-size': event.target.value}, syncFooter);
        event.target.value = '';
    });

    document.querySelector('[data-footer-table]')?.addEventListener('click', function () {
        promptAndInsertTable(editor, syncFooter);
    });

    const linkButton = document.querySelector('[data-editor-link]');
    if (linkButton) {
        linkButton.addEventListener('click', function () {
            const url = prompt('Enter link URL');
            editor.focus();
            if (url) document.execCommand('createLink', false, url);
            syncFooter();
        });
    }

    const imageButton = document.querySelector('[data-editor-image]');
    if (imageButton) {
        imageButton.addEventListener('click', function () {
            const url = prompt('Enter image URL');
            editor.focus();
            if (url) document.execCommand('insertImage', false, url);
            syncFooter();
        });
    }

    editor.addEventListener('input', syncFooter);
    const form = editor.closest('form');
    if (form) {
        form.addEventListener('submit', syncFooter);
    }
    syncFooter();
});
</script>
@endsection
