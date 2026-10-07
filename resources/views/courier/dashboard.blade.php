@extends('layouts.app')

@section('title', 'Courier Dashboard')
@section('page_title', 'Overview')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $maxWeekly = max(1, $weeklyIntake->max('count') ?: 1);
    $packageStatusPercentBase = max(1, $totalPackages);
    $canUsePackagesModule = auth()->user()->subscriptionAllowsModule('packages');
    $canUseInvoicesModule = auth()->user()->subscriptionAllowsModule('invoices');
    $canUseFinanceModule = auth()->user()->subscriptionAllowsModule('finance');
    $canUseDeliveryModule = auth()->user()->subscriptionAllowsModule('delivery');
@endphp

<style>
    .admin-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .admin-stat-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .admin-stat-card-link { display: block; color: inherit; text-decoration: none; }
    .admin-stat-card-link:hover { color: inherit; box-shadow: 0 14px 28px rgba(15, 23, 42, .08); transform: translateY(-1px); }
    .admin-stat-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .admin-stat-label { color: #64748b; font-size: 14px; font-weight: 650; margin-bottom: 4px; }
    .admin-stat-value { color: #1e293b; font-size: 30px; line-height: 1; font-weight: 900; }
    .admin-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        font-size: 20px;
    }
    .admin-trend { display: flex; align-items: center; gap: 6px; margin-top: 18px; font-size: 14px; color: #16a34a; font-weight: 750; }
    .admin-panel {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .chart-bars { height: 286px; display: flex; align-items: end; gap: 18px; padding: 22px 8px 8px; overflow-x: auto; }
    .chart-bar-wrap { flex: 1; min-width: 38px; display: grid; gap: 10px; justify-items: center; }
    .chart-bar {
        width: 100%;
        max-width: 46px;
        min-height: 8px;
        border-radius: 999px 999px 6px 6px;
        background: linear-gradient(180deg, #6366f1, #4f46e5);
        box-shadow: 0 10px 24px rgba(79, 70, 229, .18);
    }
    .chart-label { color: #64748b; font-size: 12px; font-weight: 650; }
    .chart-filter-group { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    .chart-filter {
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        color: #475569;
        font-size: 12px;
        font-weight: 800;
        padding: 7px 12px;
        text-decoration: none;
    }
    .chart-filter:hover { color: #4f46e5; border-color: #c7d2fe; background: #eef2ff; }
    .chart-filter.active { color: #fff; border-color: #4f46e5; background: #4f46e5; }
    .status-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 22px; }
    .status-summary-item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #f8fafc; }
    .status-summary-label { color: #64748b; font-size: 12px; font-weight: 750; text-transform: uppercase; }
    .status-summary-value { color: #0f172a; font-size: 22px; font-weight: 900; line-height: 1.15; }
    .status-row { display: block; margin-bottom: 24px; color: inherit; text-decoration: none; }
    .status-row:hover { color: inherit; }
    .status-row:hover .status-track { background: #e2e8f0; }
    .status-row:last-child { margin-bottom: 0; }
    .status-meta { display: flex; justify-content: space-between; align-items: end; margin-bottom: 7px; }
    .status-track { height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
    .status-fill { height: 100%; border-radius: inherit; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th {
        padding: 12px 20px;
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        text-transform: uppercase;
    }
    .admin-table td { padding: 16px 20px; border-top: 1px solid #f1f5f9; vertical-align: middle; }
    .staff-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px; border-radius: 14px; }
    .staff-row:hover { background: #f8fafc; }
    .staff-avatar {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #e2e8f0;
        color: #475569;
        font-weight: 900;
        position: relative;
    }
    .staff-dot {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 13px;
        height: 13px;
        border-radius: 999px;
        border: 2px solid #fff;
    }
    @media (max-width: 1200px) { .admin-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) {
        .admin-stat-grid { grid-template-columns: 1fr; }
        .chart-bars { gap: 9px; }
        .admin-table th, .admin-table td { padding: 12px; }
    }
</style>

<div class="admin-stat-grid mb-4">
    @if($canUsePackagesModule)
    <a class="admin-stat-card admin-stat-card-link" href="{{ $dashboardLinks['warehouse'] }}">
        <div class="admin-stat-head">
            <div><div class="admin-stat-label">Packages at Warehouses</div><div class="admin-stat-value">{{ number_format($packagesAtWarehouses) }}</div></div>
            <div class="admin-stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="fas fa-warehouse"></i></div>
        </div>
        <div class="admin-trend">
            @forelse($warehouseBreakdown as $warehouse => $count)
                <span>{{ $warehouse }}: {{ number_format($count) }}</span>@if(! $loop->last)<span>/</span>@endif
            @empty
                <span>No packages currently at warehouses</span>
            @endforelse
        </div>
    </a>
    @endif
    @if($canUseDeliveryModule)
    <a class="admin-stat-card admin-stat-card-link" href="{{ $dashboardLinks['delivery'] }}">
        <div class="admin-stat-head">
            <div><div class="admin-stat-label">Delivery Request</div><div class="admin-stat-value">{{ number_format($deliveryRequestsCount) }}</div></div>
            <div class="admin-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-truck"></i></div>
        </div>
        <div class="admin-trend">{{ number_format($pendingDeliveryRequests) }} open requests</div>
    </a>
    @endif
    @if($canUsePackagesModule)
    <a class="admin-stat-card admin-stat-card-link" href="{{ $dashboardLinks['ready'] }}">
        <div class="admin-stat-head">
            <div><div class="admin-stat-label">Ready Pickup</div><div class="admin-stat-value">{{ number_format($readyPackages) }}</div></div>
            <div class="admin-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-clock"></i></div>
        </div>
        <div class="admin-trend">Current queue</div>
    </a>
    @endif
    @if($canUseInvoicesModule || $canUseFinanceModule)
    <a class="admin-stat-card admin-stat-card-link" href="{{ $dashboardLinks['revenue'] }}">
        <div class="admin-stat-head">
            <div><div class="admin-stat-label">Total Revenue</div><div class="admin-stat-value">JMD {{ number_format($totalRevenue, 0) }}</div></div>
            <div class="admin-stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-dollar-sign"></i></div>
        </div>
        <div class="admin-trend" style="color:#dc2626">Today: JMD {{ number_format($todayRevenue, 0) }}</div>
    </a>
    @endif
</div>

@if($canUseDeliveryModule && $assignedDeliveryRequests->isNotEmpty())
    <div class="admin-panel p-4 mb-4 border border-primary-subtle">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Assigned Delivery Requests</h2>
                <div class="text-muted small">Requests currently assigned to you for handover and delivery tracking.</div>
            </div>
            <a href="{{ route('courier.delivery.index', ['assigned_to' => auth()->id()]) }}" class="btn btn-sm btn-primary">Open Delivery Queue</a>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead><tr><th>Request</th><th>Customer</th><th>Area</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($assignedDeliveryRequests as $delivery)
                    <tr>
                        <td><a class="fw-bold text-primary" href="{{ route('courier.delivery.index', ['q' => $delivery->request_number]) }}">{{ $delivery->request_number }}</a><div class="text-muted small">{{ ucfirst($delivery->delivery_type) }}</div></td>
                        <td>{{ optional(optional($delivery->customer)->user)->full_name ?: '-' }}<div class="text-muted small">{{ $delivery->mailbox ?: '-' }}</div></td>
                        <td>{{ optional($delivery->area)->name ?: '-' }}</td>
                        <td><span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $delivery->status)) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="assignedDeliveryModal" tabindex="-1" aria-labelledby="assignedDeliveryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="assignedDeliveryModalLabel">Delivery requests assigned to you</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @foreach($assignedDeliveryRequests as $delivery)
                        <a class="border rounded p-3 mb-2 d-block text-dark text-decoration-none" href="{{ route('courier.delivery.index', ['q' => $delivery->request_number]) }}">
                            <div class="fw-bold">{{ $delivery->request_number }} <span class="badge badge-soft">{{ ucfirst(str_replace('_', ' ', $delivery->status)) }}</span></div>
                            <div class="text-muted small">{{ optional(optional($delivery->customer)->user)->full_name ?: '-' }} / {{ $delivery->mailbox ?: '-' }}</div>
                            <div class="small mt-1">{{ $delivery->delivery_address ?: optional($delivery->area)->name ?: 'No address listed' }}</div>
                        </a>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <a href="{{ route('courier.delivery.index', ['assigned_to' => auth()->id()]) }}" class="btn btn-primary">View Assigned Requests</a>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="admin-panel p-4 h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Delivery Performance</h2>
                    <div class="text-muted small">{{ $chartLabel }}</div>
                </div>
                <div class="chart-filter-group">
                    @foreach($chartFilters as $filterKey => $filterLabel)
                        <a class="chart-filter {{ $chartPeriod === $filterKey ? 'active' : '' }}" href="{{ route('courier.dashboard', array_merge(request()->except('chart_period'), ['chart_period' => $filterKey])) }}">{{ $filterLabel }}</a>
                    @endforeach
                </div>
            </div>
            <div class="chart-bars">
                @foreach($weeklyIntake as $point)
                    <div class="chart-bar-wrap">
                        <div class="chart-bar" style="height: {{ 22 + round(($point['count'] / $maxWeekly) * 220) }}px"></div>
                        <div class="chart-label">{{ $point['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-panel p-4 h-100">
            <h2 class="h5 fw-bold mb-1">Package Status</h2>
            <div class="text-muted small mb-4">Current package totals</div>
            @if($canUsePackagesModule)
                <div class="status-summary">
                    <div class="status-summary-item">
                        <div class="status-summary-label">Total</div>
                        <div class="status-summary-value">{{ number_format($totalPackages) }}</div>
                    </div>
                    <div class="status-summary-item">
                        <div class="status-summary-label">Open</div>
                        <div class="status-summary-value">{{ number_format($openPackages) }}</div>
                    </div>
                </div>
            @foreach($statusBreakdown as $label => $count)
                @php
                    $percent = min(100, round(($count / $packageStatusPercentBase) * 100));
                    $color = ['Delivered' => '#16a34a', 'In Transit' => '#2563eb', 'Available' => '#4f46e5'][$label] ?? '#64748b';
                    $statusUrl = [
                        'Delivered' => $dashboardLinks['delivered'],
                        'In Transit' => $dashboardLinks['in_transit'],
                        'Available' => $dashboardLinks['available'],
                    ][$label] ?? route('courier.packages.index');
                @endphp
                <a class="status-row" href="{{ $statusUrl }}">
                    <div class="status-meta"><span class="fw-semibold">{{ $label }}</span><span class="fw-bold">{{ number_format($count) }} <span class="text-muted small">({{ $percent }}%)</span></span></div>
                    <div class="status-track"><div class="status-fill" style="width: {{ $percent }}%; background: {{ $color }}"></div></div>
                </a>
            @endforeach
            @endif
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="admin-panel overflow-hidden">
            <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 fw-bold mb-0">Customer Packages</h2>
                    <div class="text-muted small">{{ $chartLabel }}</div>
                </div>
                @if($canUsePackagesModule)
                    <a href="{{ route('courier.packages.index') }}" class="btn btn-sm btn-outline-primary">View All <i class="fas fa-chevron-right ms-1"></i></a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>Tracking / ID</th><th>Customer (Mailbox)</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>
                                @if($canUsePackagesModule)
                                    <a class="fw-bold text-primary" href="{{ route('courier.packages.show', $package) }}">{{ $package->tracking_number }}</a>
                                @else
                                    <span class="fw-bold">{{ $package->tracking_number }}</span>
                                @endif
                                <div class="text-muted small">{{ $package->supplier_tracking ?: 'PKG-'.$package->id }}</div>
                            </td>
                            <td><div class="fw-semibold">{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}</div><span class="badge bg-light text-dark">{{ $package->mailbox ?: '-' }}</span></td>
                            <td><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'No status' }}</span></td>
                            <td class="text-end">
                                @if($canUsePackagesModule)
                                    <a href="{{ route('courier.packages.show', $package) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-up-right-from-square"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No packages found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="admin-panel overflow-hidden">
            <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                <h2 class="h5 fw-bold mb-0">Branch Status</h2>
                @if(auth()->user()->user_type === 'tenant_admin')
                    <a href="{{ route('courier.outlets.index') }}" class="btn btn-sm btn-outline-secondary" title="View branches" aria-label="View branches"><i class="fas fa-code-branch"></i></a>
                @endif
            </div>
            <div class="p-2">
                @forelse($activeStaff as $staff)
                    <div class="staff-row">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="staff-avatar">{{ strtoupper(substr($staff->full_name ?: $staff->email, 0, 1)) }}<span class="staff-dot" style="background: {{ $staff->is_active ? '#22c55e' : '#ef4444' }}"></span></div>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate">{{ $staff->full_name ?: $staff->email }}</div>
                                <div class="text-muted small"><i class="fas fa-location-dot me-1"></i>{{ optional($staff->outlet)->name ?: 'All branches' }}</div>
                            </div>
                        </div>
                        <span class="badge {{ $staff->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $staff->is_active ? 'Available' : 'Inactive' }}</span>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No staff accounts found.</div>
                @endforelse
            </div>
            @if($canUseDeliveryModule)
                <div class="px-4 py-3 border-top bg-light text-center">
                    <a href="{{ route('courier.delivery.index') }}" class="fw-semibold text-muted">View Delivery Requests</a>
                </div>
            @endif
        </div>
    </div>
</div>
@if($canUseDeliveryModule && $assignedDeliveryRequests->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('assignedDeliveryModal');
    if (modal && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
</script>
@endif
@endsection



