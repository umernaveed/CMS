@extends('layouts.app')

@section('title', 'Settings')
@section('page_title', 'Settings')
@section('breadcrumb', 'Settings')

@section('content')
@php
    $fileSettings = ['app_logo', 'company_logo', 'favicon', 'registration_logo', 'registration_background_image'];
    $textareaSettings = [
        'company_address',
        'platform_tagline',
        'login_intro_text',
        'email_footer',
        'courier_carriers',
        'manifest_cargo_companies',
        'courier_bill_payment_instructions',
        'registration_business_intro',
        'registration_documents_intro',
        'registration_plan_intro',
        'registration_review_intro',
        'registration_website_offer_text',
        'registration_terms_text',
    ];
    $colorSettings = ['brand_primary_color', 'brand_secondary_color', 'registration_primary_color', 'registration_accent_color', 'registration_background_color'];
    $booleanSettings = ['courier_registration_enabled', 'automatic_backups_enabled'];
    $groupLabels = [
        'general' => 'System Settings',
        'registration' => 'Registration',
        'email' => 'Email',
        'sms' => 'SMS',
        'shipping' => 'Shipping',
        'payment' => 'Payments',
    ];
    $groupIcons = [
        'general' => 'fa-sliders',
        'registration' => 'fa-user-plus',
        'email' => 'fa-envelope',
        'sms' => 'fa-comment-sms',
        'shipping' => 'fa-truck-fast',
        'payment' => 'fa-credit-card',
    ];
    $settingPermissions = [
        'general' => 'superadmin_settings_general',
        'registration' => 'superadmin_settings_registration',
        'email' => 'superadmin_settings_email',
        'sms' => 'superadmin_settings_sms',
        'shipping' => 'superadmin_settings_shipping',
        'payment' => 'superadmin_settings_payment',
    ];
    $canManageAllSettings = auth()->user()->hasPermission('superadmin_settings');
    $canManageSettingGroup = fn ($group) => $canManageAllSettings || auth()->user()->hasPermission($settingPermissions[$group] ?? '');
    $canManageMaintenance = auth()->user()->hasPermission('superadmin_maintenance');
    $canManageMasterTech = $canManageAllSettings || auth()->user()->hasPermission('superadmin_settings_master_tech');
    $canManageReferences = $canManageAllSettings || auth()->user()->hasPermission('superadmin_settings_references');
    $settingGroups = $settings->keys()->filter(fn ($group) => $canManageSettingGroup($group))->values();
    $standaloneTabs = collect([
        $canManageMaintenance ? 'maintenance' : null,
        $canManageMasterTech ? 'master-tech' : null,
        $canManageReferences ? 'references' : null,
    ])->filter()->values();
    $firstGroup = $settingGroups->first() ?: ($standaloneTabs->first() ?: 'general');
    $currentAsset = function (?string $path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    };
@endphp

<style>
    .settings-workspace {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }
    .settings-menu {
        position: sticky;
        top: 92px;
        padding: 12px;
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
        padding: 11px 12px;
        font-weight: 800;
        text-align: left;
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
    .settings-section-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
    }
    .settings-section-head h2 {
        margin: 0 0 4px;
        font-size: 22px;
        font-weight: 900;
    }
    .settings-section-head p {
        margin: 0;
        color: #64748b;
    }
    .settings-action-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
        background: #fff;
        height: 100%;
        min-height: 138px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 10px;
    }
    .settings-action-card .btn {
        width: auto !important;
        align-self: flex-start;
        min-width: 158px;
    }
    .settings-action-card .form-control {
        max-width: 280px;
    }
    .settings-maintenance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 280px));
        gap: 14px;
        align-items: stretch;
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
    }
    @media (max-width: 576px) {
        .settings-menu-buttons {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="mb-4">
    <h1 class="h3 mb-1">General Settings</h1>
    <p class="text-muted mb-0">Manage platform configuration from the internal settings menu.</p>
</div>

<div class="settings-workspace">
    <aside class="panel settings-menu">
        <div class="settings-menu-title">General Settings</div>
        <div class="settings-menu-buttons">
            @foreach($settingGroups as $group)
                <button type="button" class="{{ $group === $firstGroup ? 'active' : '' }}" data-settings-tab="{{ $group }}">
                    <i class="fas {{ $groupIcons[$group] ?? 'fa-gear' }}"></i>
                    <span>{{ $groupLabels[$group] ?? ucwords(str_replace('_', ' ', $group)) }}</span>
                </button>
            @endforeach
            @if($canManageMaintenance)
                <button type="button" class="{{ $firstGroup === 'maintenance' ? 'active' : '' }}" data-settings-tab="maintenance">
                    <i class="fas fa-database"></i>
                    <span>Backup & Restore</span>
                </button>
            @endif
            @if($canManageMasterTech)
                <button type="button" class="{{ $firstGroup === 'master-tech' ? 'active' : '' }}" data-settings-tab="master-tech">
                    <i class="fas fa-barcode"></i>
                    <span>Master Tech Codes</span>
                </button>
            @endif
            @if($canManageReferences)
                <button type="button" class="{{ $firstGroup === 'references' ? 'active' : '' }}" data-settings-tab="references">
                    <i class="fas fa-list-check"></i>
                    <span>Reference Data</span>
                </button>
            @endif
        </div>
    </aside>

    <div>
        <form method="POST" action="{{ route('superadmin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @foreach($settings as $group => $items)
                <section class="panel p-4 mb-4 settings-section {{ $group === $firstGroup ? 'active' : '' }}" data-settings-section="{{ $group }}">
                    <div class="settings-section-head">
                        <div>
                            <h2>{{ $groupLabels[$group] ?? ucwords(str_replace('_', ' ', $group)) }}</h2>
                            <p>Update {{ strtolower($groupLabels[$group] ?? str_replace('_', ' ', $group)) }} values used across the platform.</p>
                        </div>
                        <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                    </div>
                    <div class="row g-3">
                        @foreach($items as $setting)
                            <div class="col-md-6">
                                <label class="form-label">{{ ucwords(str_replace('_', ' ', $setting->key)) }}</label>
                                @if(in_array($setting->key, $fileSettings, true))
                                    @if($currentAsset($setting->value))
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <img src="{{ $currentAsset($setting->value) }}" alt="{{ $setting->key }}" style="width: 72px; height: 72px; object-fit: contain; border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 6px;">
                                            <div class="small text-muted text-break">{{ $setting->value }}</div>
                                        </div>
                                    @endif
                                    <input type="file" name="uploads[{{ $setting->key }}]" class="form-control" accept=".jpg,.jpeg,.png,.webp{{ $setting->key === 'favicon' ? ',.ico' : '' }}{{ $setting->key === 'registration_logo' ? ',.svg' : '' }}">
                                    <div class="form-text">Leave empty to keep the current file.</div>
                                @elseif(in_array($setting->key, $textareaSettings, true))
                                    <textarea name="settings[{{ $setting->key }}]" class="form-control" rows="3">{{ old('settings.'.$setting->key, $setting->value) }}</textarea>
                                @elseif(in_array($setting->key, $colorSettings, true))
                                    <div class="d-flex gap-2">
                                        <input type="color" class="form-control form-control-color" style="width: 58px;" value="{{ old('settings.'.$setting->key, $setting->value ?: '#135fd2') }}" oninput="this.nextElementSibling.value=this.value">
                                        <input type="text" name="settings[{{ $setting->key }}]" class="form-control" value="{{ old('settings.'.$setting->key, $setting->value) }}">
                                    </div>
                                @elseif(in_array($setting->key, $booleanSettings, true))
                                    <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                    <div class="form-check form-switch pt-2">
                                        <input type="checkbox" name="settings[{{ $setting->key }}]" value="1" id="setting_{{ $setting->key }}" class="form-check-input" @checked(old('settings.'.$setting->key, $setting->value) !== '0')>
                                        <label for="setting_{{ $setting->key }}" class="form-check-label fw-bold">
                                            {{ $setting->key === 'courier_registration_enabled' ? 'Enable public courier registration' : 'Enable automatic backups' }}
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        {{ $setting->key === 'courier_registration_enabled'
                                            ? 'When disabled, public courier signup buttons are hidden and direct registration is blocked.'
                                            : 'Controls whether scheduled automatic platform backups may run.' }}
                                    </div>
                                @else
                                    <input type="{{ str_contains($setting->key, 'password') || str_contains($setting->key, 'api_key') ? 'password' : 'text' }}" name="settings[{{ $setting->key }}]" class="form-control" value="{{ old('settings.'.$setting->key, $setting->value) }}" placeholder="{{ str_contains($setting->key, 'password') || str_contains($setting->key, 'api_key') ? 'Leave blank to keep current value' : '' }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </form>

        @if($canManageMaintenance)
        <section class="panel p-4 mb-4 settings-section {{ $firstGroup === 'maintenance' ? 'active' : '' }}" data-settings-section="maintenance">
            <div class="settings-section-head">
                <div>
                    <h2>Backup & Restore</h2>
                    <p>Export, restore, sync, or clear platform testing data.</p>
                </div>
                <form method="POST" action="{{ route('superadmin.settings.personal-security-code') }}">
                    @csrf
                    <button class="btn btn-outline-primary"><i class="fas fa-key me-1"></i>Generate / Rotate Personal Code</button>
                </form>
            </div>
            <form method="POST" action="{{ route('superadmin.settings.personal-security-code.update') }}" class="settings-action-card mb-3">
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
            <div class="settings-maintenance-grid">
                <div>
                    <div class="settings-action-card">
                        <a href="{{ route('superadmin.maintenance.backup') }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Backup Platform</a>
                        <div class="form-text">Downloads a JSON snapshot for platform configuration, couriers, support, logistics, finance, and audit data.</div>
                    </div>
                </div>
                <div>
                    <div class="settings-action-card">
                        <form method="POST" action="{{ route('superadmin.maintenance.automatic-backup') }}">
                            @csrf
                            <select name="frequency" class="form-select mb-2">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            <button class="btn btn-outline-primary"><i class="fas fa-clock-rotate-left me-1"></i>Run Automatic Backup</button>
                        </form>
                        <div class="form-text">Immediate trigger using the same daily, weekly, and monthly backup engine.</div>
                    </div>
                </div>
                <div>
                    <div class="settings-action-card">
                        <form method="POST" action="{{ route('superadmin.maintenance.restore') }}" enctype="multipart/form-data" data-confirm="Restore the selected platform backup? Current platform snapshot data will be replaced.">
                            @csrf
                            <input type="file" name="backup_file" class="form-control mb-2" accept=".json,.txt" required>
                            <button class="btn btn-outline-secondary"><i class="fas fa-upload me-1"></i>Restore Backup</button>
                        </form>
                    </div>
                </div>
                <div>
                    <div class="settings-action-card">
                        <form method="POST" action="{{ route('superadmin.maintenance.sync') }}">
                            @csrf
                            <button class="btn btn-outline-primary"><i class="fas fa-rotate me-1"></i>Sync Defaults</button>
                        </form>
                        <div class="form-text">Refreshes platform defaults, courier defaults, and cached branding/settings.</div>
                    </div>
                </div>
                <div>
                    <div class="settings-action-card">
                        <form method="POST" action="{{ route('superadmin.maintenance.clear') }}" data-confirm="Clear superadmin testing data? Couriers, plans, and settings are kept.">
                            @csrf
                            <input name="confirm_text" class="form-control mb-2" placeholder="Type FRESH START" required>
                            <button class="btn btn-outline-danger"><i class="fas fa-broom me-1"></i>Clear Test Data</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if($canManageMasterTech)
        <section class="panel p-4 mb-4 settings-section {{ $firstGroup === 'master-tech' ? 'active' : '' }}" data-settings-section="master-tech">
            <div class="settings-section-head">
                <div>
                    <h2>Master Tech Access Codes</h2>
                    <p>Generate full-access maintenance codes that work across couriers until expiry or manual revocation.</p>
                </div>
            </div>
            @if(session('master_tech_code'))
                <div class="alert alert-info">
                    <div><strong>Typed code:</strong> {{ session('master_tech_code') }}</div>
                    <div class="mt-2">{!! session('master_tech_barcode') !!}</div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="window.print()">Print Barcode</button>
                </div>
            @endif
            <form method="POST" action="{{ route('superadmin.settings.master-tech-codes.store') }}" class="row g-3 mb-4">
                @csrf
                <div class="col-md-4"><label class="form-label">Label</label><input name="label" class="form-control" placeholder="Technician or device name"></div>
                <div class="col-md-3"><label class="form-label">Activation Date</label><input type="date" name="starts_at" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Expiry Date</label><input type="date" name="ends_at" class="form-control"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Generate</button></div>
            </form>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Label</th><th>Active Dates</th><th>Last Used</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    @forelse($masterTechCodes as $code)
                        <tr>
                            <td>{{ $code->label ?: 'Master Tech Code' }}</td>
                            <td>{{ optional($code->starts_at)->format('M d, Y') ?: 'Now' }} - {{ optional($code->ends_at)->format('M d, Y') ?: 'Lifetime' }}</td>
                            <td>{{ optional($code->last_used_at)->format('M d, Y h:i A') ?: '-' }}</td>
                            <td>{{ $code->revoked_at ? 'Revoked' : ($code->isUsable() ? 'Active' : 'Expired') }}</td>
                            <td class="text-end">
                                @if(! $code->revoked_at)
                                    <form method="POST" action="{{ route('superadmin.settings.master-tech-codes.revoke', $code) }}" data-confirm="Revoke this Master Tech code?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Revoke</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No Master Tech codes yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        @if($canManageReferences)
        <section class="settings-section {{ $firstGroup === 'references' ? 'active' : '' }}" data-settings-section="references">
            <div class="settings-section-head">
                <div>
                    <h2>Reference Data</h2>
                    <p>Manage shared customs office and loading port presets.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="panel p-4">
                        <h3 class="h5 mb-3">Customs Office Presets</h3>
                        <form method="POST" action="{{ route('superadmin.settings.customs-offices.store') }}" class="row g-2 mb-3">
                            @csrf
                            <div class="col-md-5"><input name="name" class="form-control" placeholder="Office name" required></div>
                            <div class="col-md-3"><input name="code" class="form-control text-uppercase" placeholder="Code" required></div>
                            <div class="col-md-4"><button class="btn btn-primary w-100">Save Office</button></div>
                            <div class="col-12"><input name="address" class="form-control" placeholder="Address"></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table mb-0"><thead><tr><th>Name</th><th>Code</th><th>Address</th></tr></thead><tbody>
                                @forelse($customsOffices as $office)
                                    <tr><td>{{ $office->name }}</td><td>{{ $office->code }}</td><td>{{ $office->address ?: '-' }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center">No customs offices yet.</td></tr>
                                @endforelse
                            </tbody></table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="panel p-4">
                        <h3 class="h5 mb-3">Loading Port Presets</h3>
                        <form method="POST" action="{{ route('superadmin.settings.loading-ports.store') }}" class="row g-2 mb-3">
                            @csrf
                            <div class="col-md-5"><input name="name" class="form-control" placeholder="Port name" required></div>
                            <div class="col-md-3"><input name="code" class="form-control text-uppercase" placeholder="Code" required></div>
                            <div class="col-md-4"><button class="btn btn-primary w-100">Save Port</button></div>
                            <div class="col-md-6"><input name="city" class="form-control" placeholder="City"></div>
                            <div class="col-md-6"><input name="country" class="form-control" placeholder="Country"></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table mb-0"><thead><tr><th>Name</th><th>Code</th><th>Location</th></tr></thead><tbody>
                                @forelse($loadingPorts as $port)
                                    <tr><td>{{ $port->name }}</td><td>{{ $port->code }}</td><td>{{ collect([$port->city, $port->country])->filter()->implode(', ') ?: '-' }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center">No loading ports yet.</td></tr>
                                @endforelse
                            </tbody></table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('[data-settings-tab]');
    const sections = document.querySelectorAll('[data-settings-section]');

    function activate(tab) {
        buttons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.settingsTab === tab);
        });
        sections.forEach(function (section) {
            section.classList.toggle('active', section.dataset.settingsSection === tab);
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            activate(button.dataset.settingsTab);
            const url = new URL(window.location.href);
            url.searchParams.set('tab', button.dataset.settingsTab);
            window.history.replaceState(null, '', url);
        });
    });

    const requestedTab = new URLSearchParams(window.location.search).get('tab') || window.location.hash.replace('#', '');
    const hasRequestedTab = Array.from(buttons).some(function (button) {
        return button.dataset.settingsTab === requestedTab;
    });
    if (requestedTab && hasRequestedTab) {
        activate(requestedTab);
    }
});
</script>
@endsection
