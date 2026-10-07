@extends('layouts.app')

@section('title', 'System Admin Dashboard')
@section('page_title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $tenantTotal = max($tenantCount ?? $tenants->total(), 1);
    $activePercent = round(($activeTenants / $tenantTotal) * 100);
    $trialPercent = round(($trialTenants / $tenantTotal) * 100);
    $suspendedPercent = round(($suspendedTenants / $tenantTotal) * 100);
    $formatCompact = function (float $value) {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1).'M';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 0).'k';
        }
        return number_format($value, 0);
    };
    $formatMoney = function (float $value) {
        return abs($value - round($value)) < 0.005 ? number_format($value, 0) : number_format($value, 2);
    };
    $statusStyles = [
        'successful' => 'bg-success-subtle text-success',
        'completed' => 'bg-success-subtle text-success',
        'paid' => 'bg-success-subtle text-success',
        'pending' => 'bg-warning-subtle text-warning',
        'failed' => 'bg-danger-subtle text-danger',
        'open' => 'bg-warning-subtle text-warning',
        'in_progress' => 'bg-primary-subtle text-primary',
        'resolved' => 'bg-success-subtle text-success',
        'closed' => 'bg-secondary-subtle text-secondary',
    ];
@endphp

<style>
    .sa-dashboard {
        display: grid;
        gap: 24px;
    }
    .sa-hero {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 32px;
        color: #fff;
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 48%, #3730a3 100%);
        box-shadow: 0 10px 24px rgba(37, 99, 235, .18);
    }
    .sa-hero::after {
        content: "";
        position: absolute;
        top: -80px;
        right: -80px;
        width: 384px;
        height: 384px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .07);
        filter: blur(34px);
    }
    .sa-hero-inner {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        gap: 24px;
        align-items: flex-start;
    }
    .sa-hero h2 {
        max-width: 760px;
        color: #fff;
        font-size: 30px;
        font-weight: 900;
        line-height: 1.15;
        margin: 0 0 10px;
    }
    .sa-hero p {
        max-width: 690px;
        margin: 0;
        color: #dbeafe;
        font-size: 14px;
        line-height: 1.65;
    }
    .sa-hero-metrics {
        width: fit-content;
        max-width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 28px;
        padding: 16px;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 12px;
        background: rgba(0, 0, 0, .22);
        backdrop-filter: blur(10px);
    }
    .sa-hero-metric {
        min-width: 150px;
    }
    .sa-hero-metric + .sa-hero-metric {
        border-left: 1px solid rgba(255, 255, 255, .18);
        padding-left: 16px;
    }
    .sa-hero-metric span {
        display: block;
        color: #bfdbfe;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .sa-hero-metric strong {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 5px;
        color: #fff;
        font-size: 24px;
        font-weight: 900;
    }
    .sa-hero-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
        flex: 0 0 auto;
    }
    .sa-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 24px;
    }
    .sa-kpi {
        display: block;
        min-height: 152px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        color: inherit;
        text-decoration: none;
        transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }
    .sa-kpi:hover {
        transform: translateY(-1px);
        border-color: #60a5fa;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
        color: inherit;
    }
    .sa-kpi-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }
    .sa-kpi-number {
        color: #1e293b;
        font-size: 30px;
        font-weight: 900;
        line-height: 1;
    }
    .sa-kpi-label {
        margin-top: 7px;
        color: #64748b;
        font-size: 14px;
        font-weight: 700;
    }
    .sa-kpi-icon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        flex: 0 0 auto;
    }
    .sa-kpi-note {
        color: #64748b;
        font-size: 12px;
    }
    .sa-chart-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
        gap: 24px;
    }
    .sa-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .sa-card-pad {
        padding: 24px;
    }
    .sa-card-title {
        margin: 0;
        color: #1e293b;
        font-size: 16px;
        font-weight: 900;
    }
    .sa-card-subtitle {
        margin: 3px 0 0;
        color: #64748b;
        font-size: 12px;
    }
    .sa-chart-box {
        height: 256px;
        width: 100%;
    }
    .sa-health-wrap {
        height: 224px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sa-health-center {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        pointer-events: none;
        padding-top: 16px;
    }
    .sa-health-center strong {
        display: block;
        color: #1e293b;
        font-size: 26px;
        line-height: 1;
    }
    .sa-health-center span {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
    }
    .sa-health-legend {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-top: 8px;
        text-align: center;
    }
    .sa-dot {
        width: 12px;
        height: 12px;
        display: block;
        margin: 0 auto 6px;
        border-radius: 50%;
    }
    .sa-list-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
    }
    .sa-list-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 24px;
        border-bottom: 1px solid #e2e8f0;
        background: rgba(248, 250, 252, .65);
    }
    .sa-list-scroll {
        max-height: 280px;
        overflow-y: auto;
    }
    .sa-list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 24px;
        border-bottom: 1px solid #f1f5f9;
        color: inherit;
        text-decoration: none;
        transition: background .16s ease;
    }
    .sa-list-row:hover {
        background: #f8fafc;
        color: inherit;
    }
    .sa-row-left {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .sa-row-icon {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        flex: 0 0 auto;
    }
    .sa-row-title {
        color: #1e293b;
        font-size: 14px;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sa-row-meta {
        color: #64748b;
        font-size: 12px;
        margin-top: 2px;
    }
    .sa-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 3px 8px;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    @media (max-width: 1200px) {
        .sa-kpi-grid,
        .sa-chart-grid,
        .sa-list-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .sa-chart-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 767px) {
        .sa-hero {
            padding: 24px;
        }
        .sa-hero-inner,
        .sa-list-head {
            display: block;
        }
        .sa-hero h2 {
            font-size: 25px;
        }
        .sa-hero-actions {
            justify-content: flex-start;
            margin-top: 18px;
        }
        .sa-hero-metric + .sa-hero-metric {
            border-left: 0;
            padding-left: 0;
        }
        .sa-kpi-grid,
        .sa-chart-grid,
        .sa-list-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="sa-dashboard">
    <section class="sa-hero">
        <div class="sa-hero-inner">
            <div>
                <h2>{{ $platformName ?? 'Courier SaaS' }} is running {{ $activeTenants }} active courier workspaces.</h2>
                <p>Monitor courier health, subscription usage, system logistics, and global revenue from your unified operating dashboard.</p>
                <div class="sa-hero-metrics">
                    <div class="sa-hero-metric">
                        <span>System Revenue (Total)</span>
                        <strong>JMD {{ $formatCompact((float) $totalRevenue) }}</strong>
                    </div>
                    <div class="sa-hero-metric">
                        <span>Shipment Revenue (Collected)</span>
                        <strong>JMD {{ $formatMoney((float) $shipmentRevenue) }}</strong>
                    </div>
                    <div class="sa-hero-metric">
                        <span>Total Packages Processed</span>
                        <strong>{{ number_format($totalPackages) }}</strong>
                    </div>
                    <div class="sa-hero-metric">
                        <span>Courier Health</span>
                        <strong>{{ $activePercent }}% <i class="fas fa-arrow-trend-up text-success fs-6"></i></strong>
                    </div>
                </div>
            </div>
            <div class="sa-hero-actions">
                <a href="{{ route('superadmin.reports.index') }}" class="btn btn-outline-light"><i class="fas fa-file-lines me-2"></i>Reports</a>
                <a href="{{ route('superadmin.tenants.create') }}" class="btn btn-light text-primary fw-bold"><i class="fas fa-plus me-2"></i>New Courier</a>
            </div>
        </div>
    </section>

    <section class="sa-kpi-grid">
        <a href="{{ route('superadmin.tenants') }}" class="sa-kpi">
            <div class="sa-kpi-top">
                <div><div class="sa-kpi-number">{{ number_format($tenantCount) }}</div><div class="sa-kpi-label">Total Couriers</div></div>
                <div class="sa-kpi-icon bg-primary-subtle text-primary"><i class="fas fa-building"></i></div>
            </div>
            <div class="sa-kpi-note"><span class="text-success fw-bold">{{ $activeTenants }} active</span> &bull; <span class="text-primary fw-bold">{{ $trialTenants }} trial</span> &bull; <span class="text-warning fw-bold">{{ $suspendedTenants }} suspended</span></div>
        </a>
        <a href="{{ route('superadmin.tenants.customers') }}" class="sa-kpi">
            <div class="sa-kpi-top">
                <div><div class="sa-kpi-number">{{ number_format($customerUsers) }}</div><div class="sa-kpi-label">End Customers</div></div>
                <div class="sa-kpi-icon bg-success-subtle text-success"><i class="fas fa-users"></i></div>
            </div>
            <div class="sa-kpi-note">Across all courier workspaces</div>
        </a>
        <a href="{{ route('superadmin.plans.index') }}" class="sa-kpi">
            <div class="sa-kpi-top">
                <div><div class="sa-kpi-number">{{ number_format($activePlans) }}</div><div class="sa-kpi-label">Active Plans</div></div>
                <div class="sa-kpi-icon bg-info-subtle text-info"><i class="fas fa-database"></i></div>
            </div>
            <div class="sa-kpi-note">Starter: {{ $starterPlans }} &bull; Business: {{ $businessPlans }} &bull; Ent: {{ $enterprisePlans }}</div>
        </a>
        <a href="{{ route('superadmin.payments.index') }}" class="sa-kpi">
            <div class="sa-kpi-top">
                <div><div class="sa-kpi-number">JMD {{ $formatCompact((float) $thisMonthRevenue) }}</div><div class="sa-kpi-label">SaaS Revenue (MRR)</div></div>
                <div class="sa-kpi-icon bg-warning-subtle text-warning"><i class="fas fa-arrow-trend-up"></i></div>
            </div>
            <div class="sa-kpi-note">Platform subscription fees this month</div>
        </a>
        <a href="{{ route('superadmin.finance.bills', ['billing_type' => 'shipment']) }}" class="sa-kpi">
            <div class="sa-kpi-top">
                <div><div class="sa-kpi-number">JMD {{ $formatMoney((float) $thisMonthShipmentRevenue) }}</div><div class="sa-kpi-label">Shipment Revenue</div></div>
                <div class="sa-kpi-icon bg-danger-subtle text-danger"><i class="fas fa-truck-fast"></i></div>
            </div>
            <div class="sa-kpi-note">Collected logistics invoice payments this month</div>
        </a>
    </section>

    <section class="sa-chart-grid">
        <div class="sa-card sa-card-pad">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="sa-card-title">Revenue Growth</h3>
                    <p class="sa-card-subtitle">6-month platform revenue</p>
                </div>
                <a href="{{ route('superadmin.payments.index') }}" class="small fw-bold">View Ledger</a>
            </div>
            <div class="sa-chart-box">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div class="sa-card sa-card-pad">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                <h3 class="sa-card-title">Courier Health</h3>
                <a href="{{ route('superadmin.tenants') }}" class="btn btn-sm btn-light">Manage</a>
            </div>
            <div class="sa-health-wrap">
                <canvas id="tenantHealthChart"></canvas>
                <div class="sa-health-center">
                    <div class="text-center">
                        <strong>{{ number_format($tenantCount) }}</strong>
                        <span>Couriers</span>
                    </div>
                </div>
            </div>
            <div class="sa-health-legend">
                <div><span class="sa-dot" style="background:#10b981"></span><div class="text-muted small fw-bold">ACTIVE</div><div class="fw-bold">{{ $activeTenants }}</div></div>
                <div><span class="sa-dot" style="background:#3b82f6"></span><div class="text-muted small fw-bold">TRIAL</div><div class="fw-bold">{{ $trialTenants }}</div></div>
                <div><span class="sa-dot" style="background:#f59e0b"></span><div class="text-muted small fw-bold">SUSPENDED</div><div class="fw-bold">{{ $suspendedTenants }}</div></div>
            </div>
        </div>
    </section>

    <section class="sa-list-grid">
        <div class="sa-card overflow-hidden">
            <div class="sa-list-head">
                <div>
                    <h3 class="sa-card-title">Recent Payments</h3>
                    <p class="sa-card-subtitle">Latest courier subscription payments</p>
                </div>
                <a href="{{ route('superadmin.payments.index') }}" class="btn btn-sm btn-light">Payments</a>
            </div>
            <div class="sa-list-scroll">
                @forelse($recentPayments as $payment)
                    @php($statusClass = $statusStyles[strtolower((string) $payment->status)] ?? 'bg-secondary-subtle text-secondary')
                    <a href="{{ route('superadmin.payments.index', ['tenant_id' => $payment->tenant_id]) }}" class="sa-list-row">
                        <div class="sa-row-left">
                            <div class="sa-row-icon"><i class="fas fa-dollar-sign"></i></div>
                            <div class="min-w-0">
                                <div class="sa-row-title">{{ optional($payment->tenant)->company_name ?? 'Deleted courier' }}</div>
                                <div class="sa-row-meta">{{ optional($payment->created_at)->diffForHumans() }} &bull; {{ $payment->transaction_id ?: $payment->id }}</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-slate-800">{{ $payment->currency }} {{ number_format($payment->amount, 0) }}</div>
                            <span class="sa-badge {{ $statusClass }}">{{ $payment->status === 'successful' ? 'Completed' : ucfirst($payment->status) }}</span>
                        </div>
                    </a>
                @empty
                    <div class="p-4 text-muted">No payments recorded yet.</div>
                @endforelse
            </div>
        </div>

        <div class="sa-card overflow-hidden">
            <div class="sa-list-head">
                <div>
                    <h3 class="sa-card-title">Support Pulse</h3>
                    <p class="sa-card-subtitle">Recent workspace tickets</p>
                </div>
                <a href="{{ route('superadmin.support.index') }}" class="small fw-bold">Open Chat <i class="fas fa-chevron-right ms-1"></i></a>
            </div>
            <div class="sa-list-scroll">
                @forelse($recentTickets as $ticket)
                    @php($statusClass = $statusStyles[strtolower((string) $ticket->status)] ?? 'bg-secondary-subtle text-secondary')
                    <a href="{{ route('superadmin.support.show', $ticket) }}" class="sa-list-row">
                        <div class="sa-row-left">
                            <div class="sa-row-icon rounded-circle bg-secondary-subtle text-secondary">{{ strtoupper(substr($ticket->subject ?: 'T', 0, 1)) }}</div>
                            <div class="min-w-0">
                                <div class="sa-row-title">{{ $ticket->subject }}</div>
                                <div class="sa-row-meta">{{ optional($ticket->tenant)->company_name ?? 'Platform' }}</div>
                            </div>
                        </div>
                        <span class="sa-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                    </a>
                @empty
                    <div class="p-4 text-muted">No support conversations yet.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const revenueCanvas = document.getElementById('revenueChart');
    if (revenueCanvas && window.Chart) {
        const gradient = revenueCanvas.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.28)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

        new Chart(revenueCanvas, {
            type: 'line',
            data: {
                labels: @json($revenueChartLabels),
                datasets: [{
                    label: 'Revenue (JMD)',
                    data: @json($revenueChartValues),
                    borderColor: '#2563eb',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.42,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: value => 'JMD ' + Number(value).toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const healthCanvas = document.getElementById('tenantHealthChart');
    if (healthCanvas && window.Chart) {
        new Chart(healthCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Trial', 'Suspended'],
                datasets: [{
                    data: [{{ $activeTenants }}, {{ $trialTenants }}, {{ $suspendedTenants }}],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { enabled: true } }
            }
        });
    }
});
</script>
@endsection

