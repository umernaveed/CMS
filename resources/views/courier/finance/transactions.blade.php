@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
@include('courier.finance._toolbar', ['title' => 'Transactions', 'subtitle' => 'Paid invoice transactions and package handoff verification.', 'section' => 'transactions'])
<form method="GET" class="panel p-3 mb-3"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Invoice or mailbox"></div>
    <div class="col-md-2"><label class="form-label">Verified</label><select name="verified" class="form-select"><option value="">All</option><option value="yes" @selected(request('verified')==='yes')>Verified</option><option value="no" @selected(request('verified')==='no')>Not verified</option></select></div>
    <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
    <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('courier.finance.transactions') }}">Reset</a></div>
</div></form>
<div class="panel"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Transaction</th><th>Customer</th><th>Packages</th><th>Paid</th><th>Total</th><th>Verified</th><th></th></tr></thead>
    <tbody>@forelse($transactions as $invoice)<tr>
        <td><strong>{{ $invoice->invoice_number }}</strong></td>
        <td>{{ optional(optional($invoice->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $invoice->mailbox }}</small></td>
        <td>{{ $invoice->items->count() }} package(s)</td>
        <td>{{ optional($invoice->paid_at)->format('M d, Y') ?? '-' }}</td>
        <td>JMD {{ number_format($invoice->total_jmd, 2) }}</td>
        <td>{!! $invoice->verified_at ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning text-dark">Pending</span>' !!}</td>
        <td class="text-end">
            @if($invoice->verified_at)
                <a href="{{ route('courier.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-secondary">View</a>
            @else
                <a href="{{ route('courier.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary">Verify</a>
            @endif
        </td>
    </tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No transactions found.</td></tr>@endforelse</tbody>
</table></div><div class="p-3">{{ $transactions->links('pagination::bootstrap-5') }}</div></div>
@endsection
