@extends('layouts.app')

@section('title', 'Top-up History')

@section('content')
@php($paymentMethods = ['online' => 'Online', 'bank_transfer' => 'Bank Transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'debit_card' => 'Debit Card', 'other' => 'Other'])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Top-up History</h1><p class="text-muted mb-0">Review and export your submitted wallet top-ups.</p></div>
    <div class="d-flex gap-2">
        <a href="{{ route('customer.wallet.top-ups.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('customer.wallet.top-ups.download', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>
        <a href="{{ route('customer.affiliate-loyalty') }}" class="btn btn-secondary">Back</a>
    </div>
</div>
<div class="panel p-3 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-3"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Reference or description"></div>
        <div class="col-md-3 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'cancelled' => 'Rejected'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3 col-lg-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select"><option value="">All methods</option>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3 col-lg-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div class="col-md-3 col-lg-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div class="col-lg-1"><button class="btn btn-primary w-100">Filter</button></div>
        <div class="col-12"><a href="{{ route('customer.wallet.top-ups.history') }}" class="small">Clear filters</a></div>
    </form>
</div>
<div class="panel overflow-hidden"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Date</th><th>Amount</th><th>Points Awarded</th><th>Method</th><th>Reference</th><th>Description</th><th>Status</th></tr></thead>
    <tbody>@forelse($topUps as $topUp)@php($statusClass = ['approved' => 'success', 'cancelled' => 'danger'][$topUp->status] ?? 'warning')<tr><td>{{ optional($topUp->created_at)->format('M d, Y h:i A') }}</td><td class="fw-bold">JMD {{ number_format((float) $topUp->amount, 2) }}</td><td>{{ $topUp->status === 'approved' ? number_format((float) $topUp->points_awarded, 2) : '-' }}</td><td>{{ $paymentMethods[$topUp->payment_method] ?? ucwords(str_replace('_', ' ', $topUp->payment_method)) }}</td><td>{{ $topUp->reference_number ?: '-' }}</td><td>{{ $topUp->description ?: '-' }}</td><td><span class="badge bg-{{ $statusClass }}">{{ $topUp->status === 'cancelled' ? 'Rejected' : ucfirst($topUp->status) }}</span></td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No top-up submissions found.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $topUps->links('pagination::bootstrap-5') }}</div>
@endsection