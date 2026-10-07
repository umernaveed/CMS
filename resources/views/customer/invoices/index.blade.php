@extends('layouts.app')

@section('title', 'My Invoices')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">My Invoices</h1>
    <p class="text-muted mb-0">Review courier charges and payment status.</p>
</div>
<form method="GET" action="{{ route('customer.invoices') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Invoice number, mailbox, notes"></div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Status</label>
            <select name="payment_status" class="form-select">
                <option value="">All</option>
                @foreach(['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Sort</label>
            <select name="sort" class="form-select">
                @foreach(['created_at' => 'Issued', 'due_date' => 'Due', 'invoice_number' => 'Invoice', 'total_jmd' => 'Total', 'payment_status' => 'Status'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'created_at') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Direction</label>
            <select name="direction" class="form-select">
                <option value="desc" @selected(request('direction', 'desc') === 'desc')>Descending</option>
                <option value="asc" @selected(request('direction') === 'asc')>Ascending</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button><a href="{{ route('customer.invoices') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Invoice</th><th>Status</th><th>Issued</th><th>Due</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td><strong>{{ $invoice->invoice_number }}</strong></td>
                    <td><span class="badge {{ $invoice->payment_status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($invoice->payment_status) }}</span></td>
                    <td>{{ optional($invoice->created_at)->format('M d, Y') }}</td>
                    <td>{{ optional($invoice->due_date)->format('M d, Y') ?? '-' }}</td>
                    <td>JMD {{ number_format($invoice->total_jmd, 2) }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="{{ route('customer.invoices.show', $invoice) }}" class="dropdown-item"><i class="fas fa-up-right-from-square me-2"></i>Open</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No invoices found yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>
@endsection


