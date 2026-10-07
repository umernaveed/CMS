{{-- customer/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Customer Dashboard')

@section('content')
<style>
    /* Customer Portal Styles matching the HTML design exactly */
    .customer-hero {
        background: #fff;
        color: #0f172a;
        border-radius: 18px;
        padding: 28px;
        box-shadow: 0 15px 45px rgba(15, 23, 42, .08);
        overflow: hidden;
        position: relative;
        border: 1px solid #e2e8f0;
    }
    .customer-hero::after {
        display: none;
    }
    .customer-hero .meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 999px;
        padding: 8px 14px;
        font-weight: 700;
        font-size: 13px;
        color: #475569;
    }
    .customer-stat {
        min-height: 118px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px;
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    .customer-stat:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    }
    .customer-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
    }
    .customer-account-avatar {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: #eef2ff;
        color: #4f46e5;
        border: 1px solid #c7d2fe;
        font-size: 20px;
        font-weight: 900;
        overflow: hidden;
        flex: 0 0 auto;
    }
    .customer-account-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Address Cards Styles - Matching HTML exactly */
    .addresses-container {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    @media (min-width: 768px) {
        .addresses-container {
            flex-direction: row;
        }
    }
    .address-sidebar {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    @media (min-width: 768px) {
        .address-sidebar {
            width: 16rem;
            border-bottom: 0;
            border-right: 1px solid #e2e8f0;
        }
    }
    .address-content {
        flex: 1;
        padding: 1.5rem;
        min-width: 0;
    }
    .country-selector, .freight-selector {
        display: flex;
        background: rgba(203, 213, 225, 0.3);
        padding: 0.25rem;
        border-radius: 0.75rem;
    }
    .country-btn, .freight-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        background: transparent;
        border: none;
        cursor: pointer;
    }
    .country-btn.active, .freight-btn.active {
        background: white;
        color: #4f46e5;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
    .address-list {
        flex-direction: column;
        gap: 0.35rem;
        background: transparent;
        padding: 0;
    }
    .address-choice-btn {
        width: 100%;
        justify-content: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        text-align: left;
        line-height: 1.15;
    }
    .address-choice-btn.active {
        border-color: #c7d2fe;
        background: #eef2ff;
        color: #4338ca;
        box-shadow: none;
    }
    .address-choice-flag {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-family: "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif;
        font-size: 22px;
        line-height: 1;
        overflow: hidden;
    }
    .address-choice-flag img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .address-choice-text {
        min-width: 0;
        display: grid;
        gap: 2px;
    }
    .address-choice-label {
        color: #1e293b;
        font-size: 14px;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .address-choice-meta {
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .address-row-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .address-row-item:last-child {
        border-bottom: none;
    }
    .address-label-item {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 0.05em;
        min-width: 130px;
    }
    .address-value-item {
        font-size: 0.875rem;
        font-weight: 500;
        color: #1e293b;
        word-break: break-word;
        overflow-wrap: anywhere;
        flex: 1;
        text-align: left;
        padding: 0 0.5rem;
    }
    .copy-field-btn {
        opacity: 0;
        transition: opacity 0.2s;
        background: #eef2ff;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        font-size: 0.7rem;
        color: #4f46e5;
        border: none;
        cursor: pointer;
        flex-shrink: 0;
    }
    .address-row-item:hover .copy-field-btn {
        opacity: 1;
    }
    .info-banner {
        background: #fef3c7;
        border: 1px solid #fde68a;
        border-radius: 0.75rem;
        padding: 1rem;
        font-size: 0.75rem;
        color: #92400e;
        margin-top: auto;
    }
    
    .quick-link-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        transition: all 0.2s ease;
        height: 100%;
        display: block;
        text-decoration: none;
    }
    .quick-link-card:hover {
        border-color: #c7d2fe;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.08);
        transform: translateY(-2px);
    }
    .quick-link-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .table-packages th {
        background: #f8fafc;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .address-token {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 999px;
        padding: 6px 10px;
        color: #4338ca;
        font-size: 12px;
        font-weight: 800;
    }
    .bank-panel {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        white-space: pre-line;
        font-size: 0.875rem;
        color: #1e293b;
    }
    .rewards-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
    }
    .reward-code {
        background: #0f172a;
        border-radius: 12px;
        color: #fff;
        font-size: 22px;
        font-weight: 900;
        padding: 14px 16px;
        text-align: center;
        overflow-wrap: anywhere;
    }
    .reward-copy-input {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #334155;
        font-size: 12px;
        padding: 10px 12px;
        overflow-wrap: anywhere;
    }
    .reward-activity {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .reward-activity:last-child {
        border-bottom: 0;
    }
    @media (max-width: 768px) {
        .address-row-item { flex-wrap: wrap; }
        .address-label-item { min-width: 100%; margin-bottom: 0.25rem; }
        .address-value-item { width: 100%; padding-left: 0; }
        .copy-field-btn { opacity: 1; }
        .customer-hero { padding: 20px; }
    }
</style>

@php
    $user = auth()->user();
    $customer = $user->customer;
    $rawMailbox = (string) ($customer->mailbox ?? ($user->mailbox ?? str_pad((string) $user->id, 5, '0', STR_PAD_LEFT)));
    $mailboxPrefix = strtoupper(trim((string) \App\Models\TenantSetting::getValue((int) $user->tenant_id, 'mailbox_prefix', '')));
    $rawMailboxUpper = strtoupper(trim($rawMailbox));
    if ($mailboxPrefix !== '' && ! \Illuminate\Support\Str::startsWith($rawMailboxUpper, $mailboxPrefix) && preg_match('/(\d+)$/', $rawMailboxUpper, $mailboxParts)) {
        $mailbox = $mailboxPrefix.str_pad($mailboxParts[1], max(5, strlen($mailboxParts[1])), '0', STR_PAD_LEFT);
    } elseif ($mailboxPrefix !== '' && ! \Illuminate\Support\Str::startsWith($rawMailboxUpper, $mailboxPrefix)) {
        $mailbox = $mailboxPrefix.$rawMailboxUpper;
    } else {
        $mailbox = $rawMailboxUpper;
    }
    $fullName = $user->full_name ?: ($customer->full_name ?? ($user->name ?? 'Customer'));
    $firstName = explode(' ', $fullName)[0];
    $profileImage = $user->image
        ? (\Illuminate\Support\Str::startsWith($user->image, ['http://', 'https://']) ? $user->image : asset($user->image))
        : null;
    
    // If no shipping addresses in database, create default ones for display
    if (!$shippingAddresses || $shippingAddresses->count() == 0) {
        // This ensures addresses always show even if database is empty
        $hasDefaultAddresses = true;
    } else {
        $hasDefaultAddresses = false;
    }
    $canUsePackages = $user->subscriptionAllowsModule('packages');
@endphp

<div class="customer-hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h2 fw-bold mb-2">Welcome back, {{ $firstName }}</h1>
            <p class="text-muted mb-0">Track arrivals, manage invoices, and copy the right warehouse address for every shipment.</p>
        </div>
        <div class="text-md-end">
            <div class="d-flex flex-wrap gap-2 justify-content-md-end mb-3">
                @if($canUsePackages)
                    <a href="{{ route('customer.pre-alerts.create') }}" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Pre-Alert
                    </a>
                    <a href="{{ route('customer.purchase-requests.create') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-receipt me-1"></i>Purchase Request
                    </a>
                    <a href="{{ route('customer.packages') }}" class="btn btn-primary">
                        <i class="fas fa-box me-1"></i>View All
                    </a>
                @endif
            </div>
            <div>
                <div class="text-muted small">Your Mailbox</div>
                <div class="h3 fw-bold mb-0">{{ $mailbox }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="customer-stat">
            <div class="customer-stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fas fa-box fa-lg"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Recent Packages</div>
                <div class="h3 mb-0 fw-bold">{{ $packages->total() ?? $packages->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="customer-stat">
            <div class="customer-stat-icon bg-success bg-opacity-10 text-success">
                <i class="fas fa-wallet fa-lg"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Wallet Balance</div>
                <div class="h3 mb-0 fw-bold">JMD {{ number_format($walletBalance ?? 0, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="customer-stat">
            <div class="customer-stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-scale-balanced fa-lg"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Outstanding</div>
                <div class="h3 mb-0 fw-bold">JMD {{ number_format($customerBalance ?? 0, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="customer-stat">
            <div class="customer-stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="fas fa-file-invoice-dollar fa-lg"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Open Invoices</div>
                <div class="h3 mb-0 fw-bold">{{ $pendingInvoices ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="customer-stat">
            <div class="customer-stat-icon bg-info bg-opacity-10 text-info">
                <i class="fas fa-truck fa-lg"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">In Transit</div>
                <div class="h3 mb-0 fw-bold">{{ $inTransitCount ?? 0 }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Mailing Addresses Section --}}
<div class="mb-5">
    <h3 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
        <i class="fas fa-map-pin text-primary"></i> Your Mailing Addresses
    </h3>

    <div class="addresses-container">
        <div class="address-sidebar">
            <div>
                <label class="text-uppercase fw-bold text-muted small mb-2 d-block">Warehouse Address</label>
                <div class="country-selector address-list">
                    @forelse($shippingAddresses as $address)
                        @php
                            $addressFlag = $address->display_flag ?: 'WH';
                            $addressMeta = collect([$address->country, $address->freight_type ? ucfirst($address->freight_type) : null])->filter()->implode(' / ');
                        @endphp
                        <button type="button" class="country-btn address-choice-btn {{ $loop->first ? 'active' : '' }}" data-address-target="address-{{ $address->id }}">
                            <span class="address-choice-flag">
                                @if($address->display_flag_image_url)
                                    <img src="{{ $address->display_flag_image_url }}" alt="{{ $address->label }} flag">
                                @else
                                    {{ $addressFlag }}
                                @endif
                            </span>
                            <span class="address-choice-text">
                                <span class="address-choice-label">{{ $address->label }}</span>
                                <span class="address-choice-meta">{{ $addressMeta ?: 'Warehouse' }}</span>
                            </span>
                        </button>
                    @empty
                        <div class="text-muted small">No mailing addresses are published yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="info-banner">
                <strong>Important:</strong> Always ensure your <strong>Mailbox Number ({{ $mailbox }})</strong> is included in the address to avoid lost packages.
            </div>
        </div>

        <div class="address-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2" id="address-title">
                    @if($shippingAddresses->count())
                        @if($shippingAddresses->first()->display_flag_image_url)
                            <img src="{{ $shippingAddresses->first()->display_flag_image_url }}" alt="" style="width:24px;height:18px;object-fit:cover;border-radius:3px;">
                        @else
                            {{ $shippingAddresses->first()->display_flag }}
                        @endif
                        {{ $shippingAddresses->first()->label }}
                    @else
                        Mailing Address
                    @endif
                </h4>
                <button type="button" class="btn btn-sm btn-outline-primary" id="copyFullAddressBtn">
                    <i class="fas fa-copy me-1"></i> Copy Full
                </button>
            </div>

            <div id="address-fields-container">
                @forelse($shippingAddresses as $address)
                    <div
                        id="address-{{ $address->id }}"
                        class="address-fields"
                        data-address-label="{{ $address->label }}"
                        data-address-flag="{{ $address->display_flag }}"
                        data-address-flag-image="{{ $address->display_flag_image_url }}"
                        style="display: {{ $loop->first ? 'block' : 'none' }};"
                    >
                        @php
                            $isChinaAddress = strtoupper(trim((string) $address->country_code)) === 'CN'
                                || strcasecmp(trim((string) $address->country), 'China') === 0;
                            $addressRows = [
                                'Name' => $address->name,
                                'Marks' => $address->marks,
                                'Address Line 1' => $address->address_1,
                                'Address Line 2' => $isChinaAddress ? null : $address->address_2,
                                'Entrance No.' => $address->entrance_no,
                                'Country' => $address->country,
                                'Province' => $address->province,
                                'City' => $address->city,
                                'State' => $address->state,
                                'Detailed Address' => $address->detailed_address,
                                'Number' => $address->number,
                                'Zip Code' => $address->zip_code,
                                'Note' => $address->note,
                            ];
                        @endphp
                        @foreach($addressRows as $label => $value)
                            @if(filled($value))
                                <div class="address-row-item">
                                    <span class="address-label-item">{{ $label }}</span>
                                    <span class="address-value-item {{ in_array($label, ['Detailed Address', 'Note']) ? 'small' : '' }}">{{ $value }}</span>
                                    <button type="button" class="copy-field-btn" data-copy="{{ $value }}">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No mailing addresses are available yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if($loyaltyEnabled ?? false)
<div class="rewards-panel mb-5">
    <div class="row g-0">
        <div class="col-lg-4 p-4 border-end">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="customer-stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-gift fa-lg"></i>
                </div>
                <div>
                    <h2 class="h5 mb-0">Earn & Loyalty</h2>
                    <div class="text-muted small">Refer friends and earn reward points.</div>
                </div>
            </div>
            <div class="reward-code mb-3">{{ $referralCode }}</div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-primary" data-copy-referral="{{ $referralUrl }}"><i class="fas fa-link me-1"></i>Copy Referral Link</button>
                <div class="reward-copy-input">{{ $referralUrl }}</div>
            </div>
        </div>
        <div class="col-lg-4 p-4 border-end">
            <div class="text-muted small fw-semibold mb-1">Available Points</div>
            <div class="display-6 fw-bold mb-3">{{ number_format($rewardPoints ?? 0, 2) }}</div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Estimated Value</span>
                <span class="fw-bold">JMD {{ number_format(($rewardPoints ?? 0) * ($pointValueJmd ?? 1), 2) }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Completed Referrals</span>
                <span class="fw-bold">{{ $referralCount ?? 0 }}</span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Referral Points</span>
                <span class="fw-bold">{{ number_format($referralPoints ?? 0, 2) }}</span>
            </div>
        </div>
        <div class="col-lg-4 p-4">
            <h3 class="h6 fw-bold mb-3">Recent Rewards</h3>
            @forelse($rewardTransactions as $transaction)
                <div class="reward-activity">
                    <div>
                        <div class="fw-semibold small">{{ $transaction->description ?: ucfirst(str_replace('_', ' ', $transaction->source_type)) }}</div>
                        <div class="text-muted small">{{ optional($transaction->created_at)->format('M d, Y') }}</div>
                    </div>
                    <div class="fw-bold {{ $transaction->transaction_type === 'credit' ? 'text-success' : 'text-danger' }}">{{ $transaction->transaction_type === 'credit' ? '+' : '-' }}{{ number_format((float) $transaction->amount, 2) }}</div>
                </div>
            @empty
                <div class="text-muted small">No points earned yet. You can earn from signup, referrals, paid invoices, and delivered packages.</div>
            @endforelse
        </div>
    </div>
</div>
@endif

@if(false)
{{-- Old fixed mailing address layout kept disabled after dynamic templates --}}
<div class="mb-5">
    <h3 class="h5 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
        <i class="fas fa-map-pin text-primary"></i> Your Mailing Addresses
    </h3>
    
    <div class="addresses-container">
        <!-- Sidebar - Country & Freight Selection -->
        <div class="address-sidebar">
            <div>
                <label class="text-uppercase fw-bold text-muted small mb-2 d-block">Destination</label>
                <div class="country-selector">
                    <button type="button" class="country-btn active" data-country="US">
                        🇺🇸 USA
                    </button>
                    <button type="button" class="country-btn" data-country="China">
                        🇨🇳 China
                    </button>
                </div>
            </div>
            
            <div>
                <label class="text-uppercase fw-bold text-muted small mb-2 d-block">Freight Type</label>
                <div class="freight-selector">
                    <button type="button" class="freight-btn active" data-method="Air">
                        <i class="fas fa-plane"></i> Air
                    </button>
                    <button type="button" class="freight-btn" data-method="Sea">
                        <i class="fas fa-ship"></i> Sea
                    </button>
                </div>
            </div>
            
            <div class="info-banner">
                <strong>Important:</strong> Always ensure your <strong>Mailbox Number ({{ $mailbox }})</strong> is included in the address to avoid lost packages.
            </div>
        </div>
        
        <!-- Address Content Area -->
        <div class="address-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2" id="address-title">
                    🇺🇸 USA Air Freight Address
                </h4>
                <button type="button" class="btn btn-sm btn-outline-primary" id="copyFullAddressBtn">
                    <i class="fas fa-copy me-1"></i> Copy Full
                </button>
            </div>
            
            <div id="address-fields-container">
                <!-- USA Air Address Fields (Visible by default) -->
                <div id="usa-air-address" class="address-fields" style="display: block;">
                    <div class="address-row-item">
                        <span class="address-label-item">Name / First Name</span>
                        <span class="address-value-item">{{ $fullName }} OLOG</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $fullName }} OLOG">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Address Line 1</span>
                        <span class="address-value-item">1807 SW 31st Ave</span>
                        <button type="button" class="copy-field-btn" data-copy="1807 SW 31st Ave">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Address Line 2</span>
                        <span class="address-value-item">{{ $mailbox }}</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $mailbox }}">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">City</span>
                        <span class="address-value-item">Hallandale</span>
                        <button type="button" class="copy-field-btn" data-copy="Hallandale">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">State</span>
                                <span class="address-value-item">Florida</span>
                                <button type="button" class="copy-field-btn" data-copy="Florida">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Zipcode</span>
                                <span class="address-value-item">33009</span>
                                <button type="button" class="copy-field-btn" data-copy="33009">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Phone</span>
                        <span class="address-value-item text-muted">Your personal number</span>
                        <button type="button" class="copy-field-btn" data-copy="">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <!-- USA Sea Address Fields -->
                <div id="usa-sea-address" class="address-fields" style="display: none;">
                    <div class="address-row-item">
                        <span class="address-label-item">Name / First Name</span>
                        <span class="address-value-item">{{ $fullName }} OLOG</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $fullName }} OLOG">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Address Line 1</span>
                        <span class="address-value-item">1807 SW 31st Ave</span>
                        <button type="button" class="copy-field-btn" data-copy="1807 SW 31st Ave">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Address Line 2</span>
                        <span class="address-value-item">{{ $mailbox }} SEA</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $mailbox }} SEA">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">City</span>
                        <span class="address-value-item">Hallandale</span>
                        <button type="button" class="copy-field-btn" data-copy="Hallandale">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">State</span>
                                <span class="address-value-item">Florida</span>
                                <button type="button" class="copy-field-btn" data-copy="Florida">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Zipcode</span>
                                <span class="address-value-item">33009</span>
                                <button type="button" class="copy-field-btn" data-copy="33009">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Phone</span>
                        <span class="address-value-item text-muted">Your personal number</span>
                        <button type="button" class="copy-field-btn" data-copy="">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <!-- China Air Address Fields -->
                <div id="china-air-address" class="address-fields" style="display: none;">
                    <div class="address-row-item">
                        <span class="address-label-item">Name</span>
                        <span class="address-value-item">{{ $fullName }}</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $fullName }}">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Marks (Crucial)</span>
                        <span class="address-value-item">{{ $mailbox }} AIR</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $mailbox }} AIR">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Entrance No.</span>
                        <span class="address-value-item">Otisian</span>
                        <button type="button" class="copy-field-btn" data-copy="Otisian">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Country</span>
                        <span class="address-value-item">China</span>
                        <button type="button" class="copy-field-btn" data-copy="China">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Province</span>
                                <span class="address-value-item">Guangdong</span>
                                <button type="button" class="copy-field-btn" data-copy="Guangdong">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">City</span>
                                <span class="address-value-item">Guangzhou</span>
                                <button type="button" class="copy-field-btn" data-copy="Guangzhou">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Detailed Address</span>
                        <span class="address-value-item small">Longsen No.5 Warehouse (Zone C1 JAJA Warehouse), No.9 Luoling Road, Daluo Village, Zhuliao Town, Baiyun District</span>
                        <button type="button" class="copy-field-btn" data-copy="Longsen No.5 Warehouse (Zone C1 JAJA Warehouse), No.9 Luoling Road, Daluo Village, Zhuliao Town, Baiyun District">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Number</span>
                                <span class="address-value-item">C1</span>
                                <button type="button" class="copy-field-btn" data-copy="C1">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Zip Code</span>
                                <span class="address-value-item">510000</span>
                                <button type="button" class="copy-field-btn" data-copy="510000">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">To</span>
                        <span class="address-value-item">Sunny Sun</span>
                        <button type="button" class="copy-field-btn" data-copy="Sunny Sun">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Mobile Phone</span>
                        <span class="address-value-item">13316185957</span>
                        <button type="button" class="copy-field-btn" data-copy="13316185957">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <!-- China Sea Address Fields -->
                <div id="china-sea-address" class="address-fields" style="display: none;">
                    <div class="address-row-item">
                        <span class="address-label-item">Name</span>
                        <span class="address-value-item">{{ $fullName }}</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $fullName }}">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Marks (Crucial)</span>
                        <span class="address-value-item">{{ $mailbox }} SEA</span>
                        <button type="button" class="copy-field-btn" data-copy="{{ $mailbox }} SEA">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Entrance No.</span>
                        <span class="address-value-item">Otisian</span>
                        <button type="button" class="copy-field-btn" data-copy="Otisian">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Country</span>
                        <span class="address-value-item">China</span>
                        <button type="button" class="copy-field-btn" data-copy="China">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Province</span>
                                <span class="address-value-item">Guangdong</span>
                                <button type="button" class="copy-field-btn" data-copy="Guangdong">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">City</span>
                                <span class="address-value-item">Guangzhou</span>
                                <button type="button" class="copy-field-btn" data-copy="Guangzhou">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Detailed Address</span>
                        <span class="address-value-item small">Longsen No.5 Warehouse (Zone C1 JAJA Warehouse), No.9 Luoling Road, Daluo Village, Zhuliao Town, Baiyun District</span>
                        <button type="button" class="copy-field-btn" data-copy="Longsen No.5 Warehouse (Zone C1 JAJA Warehouse), No.9 Luoling Road, Daluo Village, Zhuliao Town, Baiyun District">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Number</span>
                                <span class="address-value-item">C1</span>
                                <button type="button" class="copy-field-btn" data-copy="C1">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="address-row-item">
                                <span class="address-label-item">Zip Code</span>
                                <span class="address-value-item">510000</span>
                                <button type="button" class="copy-field-btn" data-copy="510000">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">To</span>
                        <span class="address-value-item">Sunny Sun</span>
                        <button type="button" class="copy-field-btn" data-copy="Sunny Sun">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="address-row-item">
                        <span class="address-label-item">Mobile Phone</span>
                        <span class="address-value-item">13316185957</span>
                        <button type="button" class="copy-field-btn" data-copy="13316185957">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endif

{{-- Recent Packages + Banking Details --}}
<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0"><i class="fas fa-boxes text-primary me-2"></i>Recent Packages</h2>
                @if($canUsePackages)
                    <a href="{{ route('customer.packages') }}" class="btn btn-outline-secondary btn-sm">Track all <i class="fas fa-arrow-right ms-1"></i></a>
                @endif
            </div>            <form method="GET" action="{{ route('customer.dashboard') }}" class="p-3 border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Tracking, merchant, courier"></div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Status</label>
                        <select name="package_status_id" class="form-select">
                            <option value="">All</option>
                            @foreach($packageStatuses as $status)
                                <option value="{{ $status->id }}" @selected((string) request('package_status_id') === (string) $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Freight</label>
                        <select name="freight_type" class="form-select">
                            <option value="">All</option>
                            @foreach(['air' => 'Air', 'sea' => 'Sea'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('freight_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Sort</label>
                        <div class="d-flex gap-2">
                            <select name="sort" class="form-select">
                                @foreach(['date_received' => 'Received', 'tracking_number' => 'Tracking', 'merchant' => 'Merchant', 'freight_type' => 'Freight', 'total_jmd' => 'Total'] as $value => $label)
                                    <option value="{{ $value }}" @selected(request('sort', 'date_received') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="direction" class="form-select">
                                <option value="desc" @selected(request('direction', 'desc') === 'desc')>Desc</option>
                                <option value="asc" @selected(request('direction') === 'asc')>Asc</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button><a href="{{ route('customer.dashboard') }}" class="btn btn-outline-secondary">Reset</a></div>
                </div>
            </form>            <div class="table-responsive">
                <table class="table table-packages mb-0">
                    <thead>
                        <tr>
                            <th>Tracking / Merchant</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Route</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>
                                <strong class="text-dark">{{ $package->tracking_number ?? 'N/A' }}</strong>
                                <div class="text-muted small mt-1">{{ $package->merchant ?? 'Unknown Merchant' }} • {{ $package->id ?? '#' }}</div>
                            </td>
                            <td>
                                {{ Str::limit($package->description ?: ($package->notes ?: 'Package'), 45) }}
                                @if($package->weight || strtolower((string) $package->freight_type) === 'sea')
                                    <div class="text-muted small mt-1">{{ $package->measureDisplay() }} • ${{ number_format($package->price_usd ?? 0, 2) }} USD</div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusName = $package->status->name ?? ($package->status_name ?? 'Processing');
                                    $statusColor = $package->status->color ?? ($package->status_color ?? '#64748b');
                                @endphp
                                <span class="status-badge" style="background: {{ $statusColor }}10; color: {{ $statusColor }}; border: 1px solid {{ $statusColor }}30;">
                                    @if(stripos($statusName, 'available') !== false)
                                        <i class="fas fa-check-circle fa-xs"></i>
                                    @elseif(stripos($statusName, 'transit') !== false)
                                        <i class="fas fa-clock fa-xs"></i>
                                    @elseif(stripos($statusName, 'uncollected') !== false)
                                        <i class="fas fa-warehouse fa-xs"></i>
                                    @else
                                        <i class="fas fa-box fa-xs"></i>
                                    @endif
                                    {{ $statusName }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1 small">
                                    @php
                                        $freightType = strtolower((string) ($package->freight_type ?? ''));
                                        $deliveryType = strtolower((string) ($package->delivery_type ?? ''));
                                        $routeLabel = $freightType === 'air'
                                            ? 'Air'
                                            : ($freightType === 'sea'
                                                ? 'Sea'
                                                : (\Illuminate\Support\Str::contains($freightType.' '.$deliveryType, ['local', 'delivery']) ? 'Local delivery' : ucfirst($freightType ?: 'Standard')));
                                        $routeIcon = $freightType === 'air' ? 'fa-plane' : ($freightType === 'sea' ? 'fa-ship' : 'fa-truck');
                                    @endphp
                                    <span>{{ $package->origin_country ?? ($package->location ?? 'US') }}</span>
                                    <i class="fas fa-arrow-right text-muted mx-1 fa-xs"></i>
                                    <i class="fas {{ $routeIcon }} text-primary"></i> {{ $routeLabel }}
                                </div>
                            </td>
                            <td class="text-end">
                                @if($canUsePackages)
                                    <a href="{{ route('customer.packages.show', $package) }}" class="btn btn-sm btn-outline-primary">
                                        Track <i class="fas fa-external-link-alt ms-1 fa-xs"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-2 d-block text-muted"></i>
                                No packages found for your mailbox yet.
                                <div class="mt-2">
                                    @if($canUsePackages)
                                        <a href="{{ route('customer.pre-alerts.create') }}" class="btn btn-sm btn-primary">Create Pre-Alert</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($packages instanceof \Illuminate\Pagination\LengthAwarePaginator && $packages->hasPages())
                <div class="p-3 border-top">
                    {{ $packages->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <div class="col-xl-4">
        {{-- Banking Details Panel --}}
        <div class="panel p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="customer-stat-icon" style="background: #eef2ff; color: #4f46e5;">
                    <i class="fas fa-building-columns fa-lg"></i>
                </div>
                <div>
                    <h2 class="h5 mb-0">Bank Transfer Details</h2>
                    <div class="text-muted small">For direct transfer payments</div>
                </div>
            </div>
            @if($bankingDetails)
                <div class="bank-panel">{!! nl2br(e($bankingDetails)) !!}</div>
                <div class="mt-3 small text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Reference: Use your mailbox ({{ $mailbox }}) and invoice number
                </div>
            @else
                <div class="bank-panel text-center text-muted">
                    <i class="fas fa-credit-card fa-2x mb-2 d-block opacity-50"></i>
                    <p class="mb-0">Bank transfer instructions will appear here when your courier publishes them.</p>
                </div>
            @endif
        </div>

        {{-- Quick Stats Card --}}
        <div class="panel p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="customer-account-avatar">
                    @if($profileImage)
                        <img src="{{ $profileImage }}" alt="{{ $fullName }}">
                    @else
                        {{ strtoupper(substr($fullName, 0, 2)) }}
                    @endif
                </div>
                <div>
                    <h2 class="h5 mb-0">Account Summary</h2>
                    <div class="text-muted small">At a glance</div>
                </div>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Mailbox Number</span>
                <span class="fw-bold">{{ $mailbox }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Customer Since</span>
                <span>{{ $customer->created_at?->format('M Y') ?? 'N/A' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Account Type</span>
                <span>{{ $customer->account_type ?? 'Personal Account' }}</span>
            </div>
        </div>
    </div>
</div>

@if(false)
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentCountry = 'US';
    let currentMethod = 'Air';
    
    // Get DOM elements
    const countryBtns = document.querySelectorAll('.country-btn');
    const freightBtns = document.querySelectorAll('.freight-btn');
    const addressTitle = document.getElementById('address-title');
    const copyFullBtn = document.getElementById('copyFullAddressBtn');
    
    // Get all address field containers
    const usaAir = document.getElementById('usa-air-address');
    const usaSea = document.getElementById('usa-sea-address');
    const chinaAir = document.getElementById('china-air-address');
    const chinaSea = document.getElementById('china-sea-address');
    
    // Function to update visible address fields
    function updateAddressDisplay() {
        // Hide all address fields first
        if (usaAir) usaAir.style.display = 'none';
        if (usaSea) usaSea.style.display = 'none';
        if (chinaAir) chinaAir.style.display = 'none';
        if (chinaSea) chinaSea.style.display = 'none';
        
        // Show the selected one
        if (currentCountry === 'US' && currentMethod === 'Air' && usaAir) {
            usaAir.style.display = 'block';
        } else if (currentCountry === 'US' && currentMethod === 'Sea' && usaSea) {
            usaSea.style.display = 'block';
        } else if (currentCountry === 'China' && currentMethod === 'Air' && chinaAir) {
            chinaAir.style.display = 'block';
        } else if (currentCountry === 'China' && currentMethod === 'Sea' && chinaSea) {
            chinaSea.style.display = 'block';
        }
        
        // Update title
        const countryFlag = currentCountry === 'US' ? '🇺🇸' : '🇨🇳';
        const countryName = currentCountry === 'US' ? 'USA' : 'China';
        if (addressTitle) {
            addressTitle.innerHTML = `${countryFlag} ${countryName} ${currentMethod} Freight Address`;
        }
    }
    
    // Country button click handlers
    countryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            countryBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCountry = this.getAttribute('data-country');
            updateAddressDisplay();
        });
    });
    
    // Freight button click handlers
    freightBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            freightBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentMethod = this.getAttribute('data-method');
            updateAddressDisplay();
        });
    });
    
    // Copy individual field functionality
    document.querySelectorAll('.copy-field-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const textToCopy = this.getAttribute('data-copy');
            if (textToCopy && textToCopy !== '') {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalHtml = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                    }, 1000);
                }).catch(() => {
                    alert('Could not copy text');
                });
            }
        });
    });
    
    // Copy full address functionality
    if (copyFullBtn) {
        copyFullBtn.addEventListener('click', function() {
            // Find the visible address fields
            let visibleFields = null;
            if (usaAir && usaAir.style.display !== 'none') visibleFields = usaAir;
            else if (usaSea && usaSea.style.display !== 'none') visibleFields = usaSea;
            else if (chinaAir && chinaAir.style.display !== 'none') visibleFields = chinaAir;
            else if (chinaSea && chinaSea.style.display !== 'none') visibleFields = chinaSea;
            
            if (visibleFields) {
                const rows = visibleFields.querySelectorAll('.address-row-item');
                let fullAddress = '';
                
                rows.forEach(row => {
                    const label = row.querySelector('.address-label-item')?.innerText || '';
                    const valueElement = row.querySelector('.address-value-item');
                    let value = valueElement?.innerText || '';
                    
                    // Clean up the value (remove any icon text)
                    value = value.replace(/Your personal number/g, '').trim();
                    
                    // Skip empty values and the phone placeholder
                    if (value && value !== '' && value !== 'Your personal number') {
                        fullAddress += `${label}: ${value}\n`;
                    }
                });
                
                if (fullAddress) {
                    navigator.clipboard.writeText(fullAddress).then(() => {
                        const originalHtml = copyFullBtn.innerHTML;
                        copyFullBtn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                        setTimeout(() => {
                            copyFullBtn.innerHTML = originalHtml;
                        }, 2000);
                    });
                }
            }
        });
    }
    
    // Initialize with default selection (USA Air)
    updateAddressDisplay();
});
</script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const addressButtons = document.querySelectorAll('.address-choice-btn');
    const addressTitle = document.getElementById('address-title');
    const copyFullBtn = document.getElementById('copyFullAddressBtn');

    function showAddress(targetId) {
        document.querySelectorAll('.address-fields').forEach(function (fields) {
            fields.style.display = fields.id === targetId ? 'block' : 'none';
            if (fields.id === targetId && addressTitle) {
                addressTitle.replaceChildren();
                if (fields.dataset.addressFlagImage) {
                    const image = document.createElement('img');
                    image.src = fields.dataset.addressFlagImage;
                    image.alt = '';
                    image.style.cssText = 'width:24px;height:18px;object-fit:cover;border-radius:3px;';
                    addressTitle.appendChild(image);
                } else if (fields.dataset.addressFlag) {
                    addressTitle.append(document.createTextNode(fields.dataset.addressFlag));
                }
                addressTitle.append(document.createTextNode(' ' + (fields.dataset.addressLabel || 'Mailing Address')));
            }
        });
    }

    addressButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            addressButtons.forEach(function (item) { item.classList.remove('active'); });
            button.classList.add('active');
            showAddress(button.dataset.addressTarget);
        });
    });

    document.querySelectorAll('.copy-field-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            const textToCopy = button.getAttribute('data-copy');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(function () {
                const originalHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function () { button.innerHTML = originalHtml; }, 1000);
            }).catch(function () {
                alert('Could not copy text');
            });
        });
    });

    if (copyFullBtn) {
        copyFullBtn.addEventListener('click', function () {
            const visibleFields = Array.from(document.querySelectorAll('.address-fields')).find(function (fields) {
                return fields.style.display !== 'none';
            });
            if (!visibleFields) return;

            let fullAddress = '';
            visibleFields.querySelectorAll('.address-row-item').forEach(function (row) {
                const label = row.querySelector('.address-label-item')?.innerText || '';
                const value = row.querySelector('.address-value-item')?.innerText || '';
                if (value) fullAddress += `${label}: ${value}\n`;
            });

            if (!fullAddress) return;
            navigator.clipboard.writeText(fullAddress).then(function () {
                const originalHtml = copyFullBtn.innerHTML;
                copyFullBtn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                setTimeout(function () { copyFullBtn.innerHTML = originalHtml; }, 2000);
            });
        });
    }

    document.querySelectorAll('[data-copy-referral]').forEach(function (button) {
        button.addEventListener('click', function () {
            const referralUrl = button.getAttribute('data-copy-referral');
            if (!referralUrl) return;
            navigator.clipboard.writeText(referralUrl).then(function () {
                const originalHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                setTimeout(function () { button.innerHTML = originalHtml; }, 2000);
            });
        });
    });
});
</script>
@endsection


