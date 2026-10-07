@extends('layouts.app')

@section('title', 'Mass Payments')
@section('page_title', 'Mass Payments')
@section('breadcrumb', 'Invoices / Mass Payments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Mass Payments</h1><p class="text-muted mb-0">Filter unpaid invoices, select multiple records, and mark them paid or unpaid together.</p></div>
    <a href="{{ route('courier.invoices.index') }}" class="btn btn-outline-secondary">Back to Invoices</a>
</div>

<div class="panel p-3 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All customers</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->mailbox }} - {{ optional($customer->user)->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
        <div class="col-md-2"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
        <div class="col-md-3"><label class="form-label">Invoice #</label><input name="invoice_number" class="form-control" value="{{ request('invoice_number') }}" placeholder="Search invoice"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">Search</button></div>
        <div class="col-md-1"><a href="{{ route('courier.invoices.mass') }}" class="btn btn-outline-secondary w-100">Clear</a></div>
    </form>
</div>

<form method="POST" action="{{ route('courier.invoices.mass.update') }}" class="panel">
    @csrf
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th><input type="checkbox" id="checkAllInvoices" class="form-check-input"></th><th>Invoice</th><th>Customer</th><th>Packages</th><th>Payment</th><th class="text-end">Amount Due</th><th>Date</th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="form-check-input mass-invoice-check" data-total="{{ (float) $invoice->total_jmd }}"></td>
                    <td><strong>{{ $invoice->invoice_number }}</strong><br><small class="text-muted">{{ ucfirst($invoice->invoice_status) }}</small></td>
                    <td>{{ optional(optional($invoice->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $invoice->mailbox }}</small></td>
                    <td>
                        {{ $invoice->items->count() }}
                        @foreach($invoice->items->take(2) as $item)
                            <div><small class="text-muted">{{ $item->tracking_number }}</small></div>
                        @endforeach
                    </td>
                    <td><span class="badge bg-warning text-dark">{{ ucfirst($invoice->payment_status) }}</span></td>
                    <td class="text-end">JMD {{ number_format($invoice->total_jmd, 2) }}</td>
                    <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No unpaid invoices found.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end fw-bold">Selected</td>
                    <td class="text-end fw-bold"><span id="selectedCount">0</span> invoices / JMD <span id="selectedTotal">0.00</span></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
        <div>{{ $invoices->links('pagination::bootstrap-5') }}</div>
        <div class="d-flex gap-2">
            <button name="payment_status" value="unpaid" class="btn btn-outline-secondary">Mark Unpaid</button>
            <button name="payment_status" value="paid" class="btn btn-primary">Delivered And Paid</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('checkAllInvoices');
    const checks = Array.from(document.querySelectorAll('.mass-invoice-check'));
    const selectedCount = document.getElementById('selectedCount');
    const selectedTotal = document.getElementById('selectedTotal');

    function refreshTotals() {
        const selected = checks.filter(check => check.checked);
        const total = selected.reduce((sum, check) => sum + Number(check.dataset.total || 0), 0);
        selectedCount.textContent = selected.length;
        selectedTotal.textContent = total.toFixed(2);
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checks.forEach(check => check.checked = checkAll.checked);
            refreshTotals();
        });
    }

    checks.forEach(check => check.addEventListener('change', refreshTotals));
    refreshTotals();
});
</script>
@endsection
