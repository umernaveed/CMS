@extends('layouts.app')

@section('title', 'Logistics')
@section('page_title', 'Logistics')
@section('breadcrumb', 'Logistics / '.ucfirst(str_replace('-', ' ', $section ?? 'overview')))

@section('content')
<style>
    .logistics-page,
    .logistics-page * {
        min-width: 0;
    }
    .logistics-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .logistics-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .logistics-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-bottom: 1rem;
    }
    .logistics-subnav a {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: .55rem .75rem;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-weight: 800;
        font-size: .88rem;
    }
    .logistics-subnav a.active {
        background: #0f766e;
        border-color: #0f766e;
        color: #fff;
    }
    .logistics-quick-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .logistics-quick-card {
        display: block;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 8px;
        background: #fff;
        padding: 1rem;
        color: #334155;
        text-decoration: none;
        min-height: 118px;
    }
    .logistics-quick-card i {
        color: #0f766e;
        font-size: 1.25rem;
        margin-bottom: .65rem;
    }
    .logistics-quick-card strong {
        display: block;
        color: #0f172a;
        margin-bottom: .35rem;
    }
    .logistics-metric {
        display: block;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 8px;
        background: #fff;
        padding: .85rem 1rem;
        min-height: 88px;
        color: inherit;
        text-decoration: none;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .logistics-metric:hover,
    .logistics-metric:focus {
        border-color: rgba(15, 118, 110, .42);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        color: inherit;
        text-decoration: none;
        transform: translateY(-1px);
    }
    .logistics-metric:focus-visible {
        outline: 3px solid rgba(15, 118, 110, .22);
        outline-offset: 2px;
    }
    .logistics-metric span {
        display: block;
        color: #64748b;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .logistics-metric strong {
        display: block;
        margin-top: .25rem;
        font-size: 1.15rem;
        line-height: 1.2;
        color: #0f172a;
        overflow-wrap: anywhere;
    }
    .logistics-workbench {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(340px, .8fr);
        gap: 1rem;
        align-items: start;
        margin-bottom: 1rem;
    }
    .logistics-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding-bottom: .85rem;
        margin-bottom: .85rem;
        border-bottom: 1px solid rgba(15, 23, 42, .08);
    }
    .logistics-panel-head h2 {
        margin: 0;
        font-size: 1rem;
    }
    .logistics-panel-head i {
        width: 2rem;
        height: 2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        color: #0f766e;
        background: #ccfbf1;
    }
    .logistics-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }
    .logistics-form-grid .wide {
        grid-column: 1 / -1;
    }
    .logistics-rate-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
        align-items: end;
        margin-bottom: .8rem;
    }
    .logistics-rate-form .rate-action {
        grid-column: 1 / -1;
    }
    .logistics-rate-note {
        color: #64748b;
        font-size: .82rem;
        margin-top: -.35rem;
        margin-bottom: .75rem;
    }
    .logistics-pricing-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
        padding-bottom: .9rem;
        margin-bottom: .9rem;
        border-bottom: 1px solid rgba(15, 23, 42, .08);
    }
    .logistics-pricing-form .wide {
        grid-column: 1 / -1;
    }
    .logistics-rate-list {
        display: grid;
        gap: .5rem;
        max-height: 290px;
        overflow-y: auto;
        padding-right: .15rem;
    }
    .logistics-rate-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: .75rem;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 8px;
        padding: .7rem .75rem;
        background: #f8fafc;
    }
    .logistics-rate-edit {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr)) auto auto;
        gap: .45rem;
        align-items: end;
    }
    .logistics-rate-range {
        display: block;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .logistics-rate-value {
        display: block;
        margin-top: .2rem;
        color: #64748b;
        font-size: .85rem;
    }
    .logistics-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) minmax(180px, 260px) 170px auto auto;
        gap: .6rem;
        align-items: end;
    }
    .logistics-bulk-form {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto auto;
        gap: .65rem;
        align-items: end;
    }
    .logistics-bulk-help {
        color: #64748b;
        font-size: .82rem;
        margin-top: .45rem;
    }
    .logistics-skip-list {
        display: grid;
        gap: .35rem;
        margin-top: .75rem;
    }
    .logistics-skip-item {
        display: grid;
        grid-template-columns: 80px minmax(120px, .7fr) minmax(120px, .8fr) minmax(160px, 1fr);
        gap: .5rem;
        padding: .5rem .65rem;
        border-radius: 8px;
        background: #fff7ed;
        color: #7c2d12;
        font-size: .82rem;
    }
    .logistics-table-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        padding: 1rem 1rem .75rem;
        border-bottom: 1px solid rgba(15, 23, 42, .08);
    }
    .logistics-table-head h2 {
        margin: 0;
        font-size: 1rem;
    }
    .logistics-table .table > :not(caption) > * > * {
        padding-top: .85rem;
        padding-bottom: .85rem;
    }
    .logistics-assign-picker {
        width: min(340px, 48vw);
        min-width: 260px;
    }
    .logistics-assign-picker select,
    .logistics-assign-picker input {
        width: 100%;
    }    .logistics-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border-radius: 999px;
        padding: .25rem .55rem;
        background: #e0f2fe;
        color: #0369a1;
        font-size: .75rem;
        font-weight: 700;
    }
    @media (max-width: 1199.98px) {
        .logistics-workbench,
        .logistics-bulk-form,
        .logistics-filter {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 767.98px) {
        .logistics-head,
        .logistics-table-head {
            flex-direction: column;
            align-items: stretch;
        }
        .logistics-metrics,
        .logistics-quick-grid,
        .logistics-form-grid,
        .logistics-pricing-form,
        .logistics-rate-form {
            grid-template-columns: 1fr;
        }
        .logistics-rate-edit {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="logistics-page">
@php($section = $section ?? 'overview')
<div class="logistics-head">
    <div>
        <h1 class="h3 mb-1">Warehouse & Logistics</h1>
        <p class="text-muted mb-0">Use the workflow pages below for manifests, uploads, pricing, and invoice review.</p>
    </div>
</div>

<div class="logistics-subnav">
    <a class="{{ $section === 'overview' ? 'active' : '' }}" href="{{ route('superadmin.logistics.index') }}"><i class="fas fa-chart-line"></i>Overview</a>
    <a class="{{ $section === 'manifests' ? 'active' : '' }}" href="{{ route('superadmin.logistics.manifests') }}"><i class="fas fa-clipboard-list"></i>Manifests</a>
    <a class="" href="{{ route('superadmin.logistics.bulk-check-in') }}"><i class="fas fa-barcode"></i>Bulk Check-In</a>
    <a class="{{ $section === 'bulk' ? 'active' : '' }}" href="{{ route('superadmin.logistics.bulk-upload') }}"><i class="fas fa-file-upload"></i>Bulk Upload</a>
    <a class="{{ $section === 'rates' ? 'active' : '' }}" href="{{ route('superadmin.logistics.rates') }}"><i class="fas fa-balance-scale"></i>Rates & Pricing</a>
    <a class="{{ $section === 'packages' ? 'active' : '' }}" href="{{ route('superadmin.logistics.packages') }}"><i class="fas fa-boxes-stacked"></i>Packages</a>
</div>

<div class="logistics-metrics">
    <a class="logistics-metric" href="{{ route('superadmin.logistics.manifests') }}#manifests-list"><span>Manifests</span><strong>{{ number_format($stats['manifests']) }}</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.logistics.manifests', ['status' => 'received']) }}#manifests-list"><span>In Progress</span><strong>{{ number_format($stats['received']) }}</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.finance.bills', ['billing_type' => 'shipment', 'status' => 'unpaid']) }}"><span>Unpaid Shipment Invoices</span><strong class="text-danger">JMD {{ number_format($stats['unpaid'], 2) }}</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.finance.bills', ['billing_type' => 'shipment', 'status' => 'paid']) }}"><span>Paid Shipment Invoices</span><strong class="text-success">JMD {{ number_format($stats['paid'], 2) }}</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.logistics.packages') }}#packages-list"><span>Packages Uploaded</span><strong>{{ number_format($stats['packages_uploaded']) }}</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.logistics.packages', ['assignment' => 'unknown']) }}#packages-list"><span>Unassigned Packages</span><strong class="text-warning">{{ number_format($stats['packages_unassigned']) }}</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.logistics.packages') }}#packages-list"><span>Total Air Weight</span><strong>{{ number_format($stats['total_weight_lb'], 2) }} lb / {{ number_format($stats['total_weight_kg'], 2) }} kg</strong></a>
    <a class="logistics-metric" href="{{ route('superadmin.logistics.packages') }}#packages-list"><span>Total Sea Volume</span><strong>{{ number_format($stats['total_volume_cuft'] ?? 0, 2) }} cu.ft / {{ number_format($stats['total_volume_cbm'] ?? 0, 3) }} CBM</strong></a>
</div>

@if($section === 'overview')
    <div class="logistics-quick-grid">
        <a class="logistics-quick-card" href="{{ route('superadmin.logistics.manifests') }}"><i class="fas fa-clipboard-list"></i><strong>Manifests</strong><span>Create manifests, open package lists, scan check-in, and check out paid cargo.</span></a>
        <a class="logistics-quick-card" href="{{ route('superadmin.logistics.bulk-check-in') }}"><i class="fas fa-barcode"></i><strong>Bulk Check-In</strong><span>Scan arriving packages once and update courier/customer statuses automatically.</span></a>
        <a class="logistics-quick-card" href="{{ route('superadmin.logistics.bulk-upload') }}"><i class="fas fa-file-upload"></i><strong>Bulk Upload</strong><span>Upload package CSV files and distribute rows by customer mailbox.</span></a>
        <a class="logistics-quick-card" href="{{ route('superadmin.logistics.rates') }}"><i class="fas fa-balance-scale"></i><strong>Rates & Pricing</strong><span>Maintain warehouse ranges, service fee logic, freight rates, and USD/JMD.</span></a>
        <a class="logistics-quick-card" href="{{ route('superadmin.finance.bills', ['billing_type' => 'shipment']) }}"><i class="fas fa-file-invoice-dollar"></i><strong>Shipment Invoices</strong><span>Review shipment bills, payments, receipts, discounts, and printouts in Courier Billing.</span></a>
        <a class="logistics-quick-card" href="{{ route('superadmin.logistics.packages') }}"><i class="fas fa-boxes-stacked"></i><strong>Packages</strong><span>View every uploaded package, resolve Unknown Room rows, and export system-wide package data.</span></a>
    </div>
@endif

@if($section === 'manifests')
<div class="logistics-workbench">
    <div class="panel p-3 p-lg-4">
        <div class="logistics-panel-head">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-clipboard-list"></i>
                <h2>Create Manifest</h2>
            </div>
        </div>
        <form method="POST" action="{{ route('superadmin.logistics.manifests.store') }}" class="logistics-form-grid">
            @csrf
            <div>
                <label class="form-label">Courier</label>
                <select name="tenant_id" class="form-select" required>
                    <option value="">Select courier</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Manifest #</label>
                <input name="manifest_number" class="form-control" placeholder="Auto if blank">
            </div>
            <div>
                <label class="form-label">Warehouse Reference</label>
                <input name="warehouse_reference" class="form-control">
            </div>
            <div>
                <label class="form-label">Cargo Company</label>
                <input name="cargo_company" class="form-control">
            </div>
            <div>
                <label class="form-label">Received At</label>
                <input type="datetime-local" name="received_at" class="form-control">
            </div>
            <div class="d-flex align-items-end">
                <div class="form-check mb-2">
                    <input type="hidden" name="require_checkout_scan" value="0">
                    <input class="form-check-input" type="checkbox" name="require_checkout_scan" value="1" id="scanRequired" checked>
                    <label class="form-check-label" for="scanRequired">Require scan before checkout</label>
                </div>
            </div>
            <div class="wide">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="wide">
                <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Manifest</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($section === 'bulk')
<div class="panel p-3 mb-3">
    <div class="logistics-panel-head">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-file-upload"></i>
            <h2>Bulk Upload Packages</h2>
        </div>
    </div>
    <form method="POST" action="{{ route('superadmin.logistics.packages.bulk-upload') }}" enctype="multipart/form-data" class="logistics-bulk-form">
        @csrf
        <div>
            <label class="form-label">CSV File</label>
            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
            <div class="logistics-bulk-help">Required columns: mailbox, tracking_number, weight, weight_unit. Each row is assigned to the customer and courier found by mailbox.</div>
        </div>
        <div>
            <label class="form-label">Fallback Courier</label>
            <select name="fallback_tenant_id" class="form-select">
                <option value="">Only use CSV courier column</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->company_name }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('superadmin.logistics.packages.bulk-template') }}" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Template</a>
        <button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button>
    </form>
    @if(session('bulk_upload_skips'))
        <div class="logistics-skip-list">
            @foreach(session('bulk_upload_skips') as $skip)
                <div class="logistics-skip-item">
                    <strong>Row {{ $skip['row'] ?: '-' }}</strong>
                    <span>{{ $skip['mailbox'] ?: 'No mailbox' }}</span>
                    <span>{{ $skip['tracking_number'] ?: 'No tracking' }}</span>
                    <span>{{ $skip['reason'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endif

@if($section === 'manifests')
<div class="panel p-3 mb-3">
    <form method="GET" class="logistics-filter">
        <div>
            <label class="form-label">Search</label>
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Manifest, cargo, warehouse ref">
        </div>
        <div>
            <label class="form-label">Courier</label>
            <select name="tenant_id" class="form-select">
                <option value="">All couriers</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['received','checked_in','checked_out'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
        <a href="{{ route('superadmin.logistics.index') }}" class="btn btn-outline-secondary">Reset</a>
    </form>
</div>

<div class="panel logistics-table mb-4" id="manifests-list">
    <div class="logistics-table-head">
        <h2>Manifests</h2>
        <span class="text-muted small">{{ $manifests->total() }} total</span>
    </div>
    <div class="table-responsive" data-no-datatable>
        <table class="table mb-0 align-middle no-datatable" data-no-datatable>
            <thead><tr><th>Manifest</th><th>Courier</th><th>Cargo</th><th>Received</th><th class="text-end">Packages</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($manifests as $manifest)
                <tr>
                    <td><strong>{{ $manifest->manifest_number }}</strong><br><small class="text-muted">{{ $manifest->warehouse_reference ?: 'No warehouse ref' }}</small></td>
                    <td>{{ optional($manifest->tenant)->company_name }}</td>
                    <td>{{ $manifest->cargo_company ?: '-' }}</td>
                    <td>{{ optional($manifest->received_at)->format('M d, Y h:i A') ?: '-' }}</td>
                    <td class="text-end">{{ number_format($manifest->packages_count) }}</td>
                    <td><span class="logistics-status">{{ ucfirst(str_replace('_', ' ', $manifest->status)) }}</span></td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="{{ route('superadmin.logistics.show', $manifest) }}" class="btn btn-sm btn-outline-primary">Open / Edit</a>
                            @if($manifest->packages_count === 0)
                                <form method="POST" action="{{ route('superadmin.logistics.manifests.destroy', $manifest) }}" data-confirm="Delete this manifest?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No logistics manifests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $manifests->links('pagination::bootstrap-5') }}</div>
</div>
@endif

@if($section === 'packages')
<div class="panel p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Tracking, mailbox, client, courier"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Courier</label><select name="tenant_id" class="form-select"><option value="">All couriers</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Assignment</label><select name="assignment" class="form-select"><option value="">All</option><option value="assigned" @selected(request('assignment') === 'assigned')>Assigned</option><option value="unknown" @selected(request('assignment') === 'unknown')>Unknown</option></select></div>
        <div class="col-lg-1 col-md-6"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i></button></div>
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="{{ route('superadmin.logistics.packages.export-all', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('superadmin.logistics.packages.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('superadmin.logistics.packages.labels', request()->query()) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-tags me-1"></i>Labels</a>
        <a href="{{ route('superadmin.logistics.packages.download', request()->query()) }}" class="btn btn-primary"><i class="fas fa-download me-1"></i>Download</a>
        <a href="{{ route('superadmin.logistics.packages') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</div>

<div class="panel logistics-table mb-4" id="packages-list">
    <div class="logistics-table-head"><h2>System Packages</h2><span class="text-muted small">{{ $packages->total() }} total</span></div>
    <div class="table-responsive" data-no-datatable>
        <table class="table mb-0 align-middle no-datatable" data-no-datatable>
            <thead><tr><th>Uploaded</th><th>Tracking</th><th>Courier</th><th>Client / Mailbox</th><th>Weight / Volume</th><th>Total</th><th>Assignment</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                @php($unknown = $package->location_status === 'inhouse_unknown_room' || ! $package->courier_package_id)
                <tr>
                    <td>{{ optional($package->created_at)->format('M d, Y') }}</td>
                    <td><strong>{{ $package->tracking_number }}</strong><br><small class="text-muted">HAWB: {{ optional($package->courierPackage)->tracking_number ?: '-' }}</small></td>
                    <td>{{ optional($package->tenant)->company_name ?: '-' }}<br><small class="text-muted">{{ optional($package->manifest)->manifest_number ?: '-' }}</small></td>
                    <td>{{ optional(optional(optional($package->courierPackage)->customer)->user)->full_name ?: '-' }}<br><small class="text-muted">{{ optional($package->courierPackage)->mailbox ?: ($package->location_notes ?: 'No mailbox assigned') }}</small></td>
                    <td>{{ $package->measureDisplay() }}</td>
                    <td>JMD {{ number_format((float) $package->amount, 2) }}</td>
                    <td><span class="badge {{ $unknown ? 'bg-warning text-dark' : 'bg-success' }}">{{ $unknown ? 'Unknown' : 'Assigned' }}</span></td>
                    <td class="text-end">
                        @if($unknown)
                            <div class="d-flex gap-1 justify-content-end align-items-center">
                                <a href="{{ route('superadmin.logistics.packages.label', $package) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print label"><i class="fas fa-tag"></i></a>
                                <form method="POST" action="{{ route('superadmin.logistics.packages.assign', $package) }}" class="d-flex gap-1 justify-content-end">
                                    @csrf @method('PATCH')
                                    <div class="logistics-assign-picker">
                                        <input type="search" class="form-control form-control-sm mb-1" data-search-select="customer_id" data-remote-url="{{ route('superadmin.logistics.packages.customers.search') }}" placeholder="Search mailbox, client, courier">
                                        <select name="customer_id" class="form-select form-select-sm" required>
                                            <option value="">Type at least 2 characters</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary">Assign</button>
                                </form>
                            </div>
                        @else
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('superadmin.logistics.packages.label', $package) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-tag me-1"></i>Label</a>
                                @if($package->manifest)
                                    <a href="{{ route('superadmin.logistics.show', $package->manifest) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                                @else
                                    <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">No manifest</span>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No logistics packages found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $packages->links('pagination::bootstrap-5') }}</div>
</div>
@endif
@if($section === 'rates')
<div class="panel p-3 p-lg-4 mb-3">
    <div class="logistics-panel-head">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-balance-scale"></i>
            <h2>Rates & Pricing</h2>
        </div>
    </div>
    <div class="logistics-rate-note">USD package total is converted to JMD when package rows are created.</div>
    <form method="POST" action="{{ route('superadmin.logistics.pricing.update') }}" class="logistics-pricing-form">
        @csrf
        <div>
            <label class="form-label">Service Fee Type</label>
            <select name="service_fee_type" class="form-select">
                <option value="flat" @selected(($pricing['service_fee_type'] ?? 'flat') === 'flat')>Flat amount per package</option>
                <option value="percentage" @selected(($pricing['service_fee_type'] ?? 'flat') === 'percentage')>Percentage of warehouse fee</option>
            </select>
        </div>
        <div>
            <label class="form-label">Service Fee Value</label>
            <input name="service_fee_value" type="number" step="0.01" min="0" class="form-control" value="{{ $pricing['service_fee_value'] ?? 0 }}" placeholder="0.10 or 5">
        </div>
        <div><label class="form-label">Freight Rate per KG</label><input name="freight_rate_kg" type="number" step="0.01" min="0" class="form-control" value="{{ $pricing['freight_rate_kg'] ?? 0 }}" placeholder="3.50"></div>
        <div><label class="form-label">Freight Rate per LB</label><input name="freight_rate_lb" type="number" step="0.01" min="0" class="form-control" value="{{ $pricing['freight_rate_lb'] ?? 0 }}" placeholder="1.59"></div>
        <div>
            <label class="form-label">USD/JMD</label>
            <input name="exchange_rate_jmd" type="number" step="0.0001" min="0" class="form-control" value="{{ $pricing['exchange_rate_jmd'] ?? 0 }}" placeholder="Leave 0 to fetch daily FX rate">
        </div>
        <div class="wide">
            <button class="btn btn-outline-primary"><i class="fas fa-save me-1"></i>Save Pricing</button>
        </div>
    </form>
    <div class="logistics-rate-note">Warehouse / manifest rate ranges are USD rates by method and region. AIR is flat per package; SEA is multiplied by cu.ft or CBM volume, with Min Billable defaulting to Min. Leave Max blank to continue the last range indefinitely.</div>
    <form method="POST" action="{{ route('superadmin.logistics.rates.store') }}" class="logistics-rate-form">
        @csrf
        <div><label class="form-label">Method</label><select name="shipment_type" class="form-select" data-logistics-rate-method><option value="air" selected>AIR</option><option value="sea">SEA</option></select></div>
        <div><label class="form-label">Region</label><select name="region" class="form-select"><option value="USA" selected>USA</option><option value="China">China</option><option value="">All Regions</option></select></div>
        <div><label class="form-label">Min</label><input name="min_weight" type="number" step="0.01" min="0" class="form-control" placeholder="0.01" required></div>
        <div><label class="form-label">Max</label><input name="max_weight" type="number" step="0.01" min="0" class="form-control" placeholder="Leave blank for up"></div>
        <div><label class="form-label">Unit</label><select name="weight_unit" class="form-select" data-logistics-rate-unit><option value="lb">lb</option><option value="kg">kg</option><option value="cuft">cu.ft</option><option value="cbm">CBM</option></select></div>
        <div><label class="form-label">Min Billable</label><input name="minimum_billable_measure" type="number" step="0.01" min="0" class="form-control" placeholder="Defaults to Min"></div>
        <div><label class="form-label">USD Rate</label><input name="rate_per_unit" type="number" step="0.01" min="0" class="form-control" placeholder="19.80" required></div>
        <div class="rate-action"><button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Rate</button></div>
    </form>
    <div class="logistics-rate-list">
        @forelse($rates as $rate)
            <div class="logistics-rate-row">
                <form method="POST" action="{{ route('superadmin.logistics.rates.update', $rate) }}" class="logistics-rate-edit">
                    @csrf @method('PUT')
                    <div><label class="form-label small">Method</label><select name="shipment_type" class="form-select form-select-sm" data-logistics-rate-method><option value="air" @selected(($rate->shipment_type ?? 'air') === 'air')>AIR</option><option value="sea" @selected(($rate->shipment_type ?? 'air') === 'sea')>SEA</option></select></div>
                    <div><label class="form-label small">Region</label><select name="region" class="form-select form-select-sm"><option value="USA" @selected(($rate->region ?? 'USA') === 'USA')>USA</option><option value="China" @selected(($rate->region ?? '') === 'China')>China</option><option value="" @selected(blank($rate->region))>All</option></select></div>
                    <div><label class="form-label small">Min</label><input name="min_weight" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->min_weight }}" required></div>
                    <div><label class="form-label small">Max</label><input name="max_weight" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->max_weight }}"></div>
                    <div><label class="form-label small">Unit</label><select name="weight_unit" class="form-select form-select-sm" data-logistics-rate-unit><option value="lb" @selected($rate->weight_unit === 'lb')>lb</option><option value="kg" @selected($rate->weight_unit === 'kg')>kg</option><option value="cuft" @selected($rate->weight_unit === 'cuft')>cu.ft</option><option value="cbm" @selected($rate->weight_unit === 'cbm')>CBM</option></select></div>
                    <div><label class="form-label small">Min Billable</label><input name="minimum_billable_measure" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->minimum_billable_measure ?: $rate->min_weight }}"></div>
                    <div><label class="form-label small">USD Rate</label><input name="rate_per_unit" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rate->rate_per_unit }}" required></div>
                    <div class="d-flex align-items-end pb-1"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="rateActive{{ $rate->id }}" @checked($rate->is_active)><label class="form-check-label small" for="rateActive{{ $rate->id }}">Active</label></div></div>
                    <button class="btn btn-sm btn-outline-primary" aria-label="Save warehouse rate"><i class="fas fa-save"></i></button>
                </form>
                <form method="POST" action="{{ route('superadmin.logistics.rates.destroy', $rate) }}" data-confirm="Delete this warehouse rate?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="Delete warehouse rate"><i class="fas fa-trash"></i></button></form>
            </div>
        @empty
            <div class="text-center text-muted py-3">No rates yet.</div>
        @endforelse
    </div>
</div>
@endif

@if($section === 'invoices')
@php($invoiceStatuses = ['' => 'All', 'unpaid' => 'Unpaid', 'paid' => 'Paid', 'issued' => 'Issued', 'void' => 'Void'])
<div class="logistics-subnav mb-3">
    @foreach($invoiceStatuses as $statusKey => $statusLabel)
        <a class="{{ request('invoice_status', '') === $statusKey ? 'active' : '' }}" href="{{ route('superadmin.logistics.invoices', array_filter(array_merge(request()->except('page'), ['invoice_status' => $statusKey]), fn($value) => $value !== null && $value !== '')) }}">{{ $statusLabel }}</a>
    @endforeach
</div>
<div class="panel logistics-table" id="invoices-list">
    <div class="logistics-table-head">
        <h2>Logistics Invoices</h2>
        <span class="text-muted small">{{ $invoices->count() }} shown</span>
    </div>
    <div class="table-responsive" data-no-datatable>
        <table class="table mb-0 align-middle no-datatable" data-no-datatable>
            <thead><tr><th>Invoice</th><th>Courier</th><th>Manifest</th><th>Status</th><th>Files</th><th class="text-end">Total</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td><strong>{{ $invoice->invoice_number }}</strong><br><small class="text-muted">{{ optional($invoice->created_at)->format('M d, Y') }}</small></td>
                    <td>{{ optional($invoice->tenant)->company_name }}</td>
                    <td>{{ optional($invoice->manifest)->manifest_number ?: '-' }}<br><small class="text-muted">AWB/BOL: {{ optional($invoice->manifest)->warehouse_reference ?: '-' }}</small></td>
                    <td><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : ($invoice->status === 'void' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ ucfirst($invoice->status) }}</span></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            @if($invoice->supplier_invoice_path)
                                <a href="{{ route('superadmin.logistics.invoices.supplier-invoice.view', $invoice) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Supplier File</a>
                            @endif
                            @if($invoice->receipt_path)
                                <a href="{{ route('superadmin.logistics.invoices.receipt.view', $invoice) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Payment Proof</a>
                            @endif
                            @if(! $invoice->supplier_invoice_path && ! $invoice->receipt_path)
                                <span class="text-muted small">-</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-end">JMD {{ number_format($invoice->total, 2) }}</td>
                    <td class="text-end">
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="{{ route('superadmin.logistics.invoices.download', $invoice) }}" target="_blank">Full Invoice</a>
                                <a class="dropdown-item" href="{{ route('superadmin.logistics.invoices.thermal', $invoice) }}" target="_blank">View Thermal</a>
                                <a class="dropdown-item" href="{{ route('superadmin.logistics.invoices.thermal.download', $invoice) }}">Download</a>
                                <form method="POST" action="{{ route('superadmin.logistics.invoices.send', $invoice) }}">@csrf<button class="dropdown-item" type="submit">Email</button></form>
                                @if($invoice->status !== 'void')
                                    <form method="POST" action="{{ route('superadmin.logistics.invoices.void', $invoice) }}" data-confirm="Void this logistics invoice? It will be permanently locked.">@csrf<button class="dropdown-item text-danger" type="submit">Void</button></form>
                                @endif
                                @if($invoice->status !== 'paid' && $invoice->status !== 'void')
                                    <form method="POST" action="{{ route('superadmin.logistics.invoices.paid', $invoice) }}">@csrf<button class="dropdown-item text-success" type="submit">Mark Paid</button></form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No invoices found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-logistics-rate-method]').forEach(function (methodSelect) {
        const form = methodSelect.closest('form');
        const unitSelect = form ? form.querySelector('[data-logistics-rate-unit]') : null;
        if (!unitSelect) return;

        function syncUnits() {
            const allowed = methodSelect.value === 'sea' ? ['cuft', 'cbm'] : ['lb', 'kg'];
            Array.from(unitSelect.options).forEach(function (option) {
                option.hidden = !allowed.includes(option.value);
            });
            if (!allowed.includes(unitSelect.value)) {
                unitSelect.value = allowed[0];
            }
        }

        methodSelect.addEventListener('change', syncUnits);
        syncUnits();
    });
});
</script>
@endsection

