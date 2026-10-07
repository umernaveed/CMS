@extends('layouts.app')

@section('title', 'Payments')
@section('page_title', 'Payments')
@section('breadcrumb', 'Payments')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Invoices & Payments</h1>
        <p class="text-muted mb-0">Review subscription payments, courier service charge payments, payment status, and SaaS revenue trends.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('superadmin.reports.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Extract</a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="panel p-3"><div class="text-muted">SaaS Revenue</div><div class="h3 mb-0">JMD {{ number_format($totals['successful'], 2) }}</div><small>Subscriptions and paid courier service charges</small></div></div>
    <div class="col-md-4"><div class="panel p-3"><div class="text-muted">Pending Amount</div><div class="h3 mb-0">JMD {{ number_format($totals['pending'], 2) }}</div></div></div>
    <div class="col-md-4"><div class="panel p-3"><div class="text-muted">Failed Payments</div><div class="h3 mb-0">{{ $totals['failed'] }}</div></div></div>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5 mb-3">Payment Revenue Chart</h2>
    <div style="height:280px"><canvas id="paymentChart"></canvas></div>
</div>

<div class="panel mb-4 p-3">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Courier</label>
            <select name="tenant_id" class="form-select">
                <option value="">All couriers</option>
                @foreach($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['pending', 'successful', 'failed', 'refunded'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('superadmin.payments.index') }}" class="btn btn-outline-secondary w-100">Reset</a></div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Courier</th><th>Plan / Source</th><th>Transaction</th><th>Method</th><th>Status</th><th>Paid At</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><strong>{{ optional($payment->tenant)->company_name ?? 'Deleted courier' }}</strong></td>
                    <td>{{ str_starts_with((string) $payment->transaction_id, 'SAB-') ? 'Courier service charge' : (optional(optional($payment->subscription)->plan)->name ?? '-') }}</td>
                    <td>{{ $payment->transaction_id ?: '-' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                    <td><span class="badge badge-soft">{{ ucfirst($payment->status) }}</span></td>
                    <td>{{ optional($payment->paid_at)->format('M d, Y h:i A') ?? '-' }}</td>
                    <td class="text-end">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No payments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $payments->links('pagination::bootstrap-5') }}</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('paymentChart'), {
        type: 'line',
        data: {
            labels: @json($paymentChart['labels']),
            datasets: [
                { label: 'Successful', data: @json($paymentChart['successful']), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.12)', fill: true, tension: .35 },
                { label: 'Pending', data: @json($paymentChart['pending']), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.12)', fill: true, tension: .35 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>
@endsection
