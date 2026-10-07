@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Invoices</h1><p class="text-muted mb-0">Billing and payment status for customers.</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('courier.invoices.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('courier.invoices.print', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.invoices.mass') }}" class="btn btn-outline-primary"><i class="fas fa-list-check me-1"></i>Mass Payments</a>
        <form id="sendSelectedInvoices" method="POST" action="{{ route('courier.invoices.send-selected') }}" class="d-none">@csrf</form>
        <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#bulkInvoicePreview"><i class="fas fa-layer-group me-1"></i>Bulk Create</button>
        <button class="btn btn-outline-primary" type="submit" form="sendSelectedInvoices" data-confirm="Send the selected invoices to customers?"><i class="fas fa-paper-plane me-1"></i>Send Selected</button>
        <a href="{{ route('courier.invoices.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Create Invoice</a>
    </div>
</div>
<form method="GET" action="{{ route('courier.invoices.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Invoice, mailbox, customer"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Payment</label><select name="payment_status" class="form-select"><option value="">All</option>@foreach(['unpaid','partial','paid','cancelled'] as $status)<option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Invoice</label><select name="invoice_status" class="form-select"><option value="">All</option>@foreach(['draft','issued','cancelled'] as $status)<option value="{{ $status }}" @selected(request('invoice_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div class="col-lg-1 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter"></i></button><a href="{{ route('courier.invoices.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>
<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn {{ request('payment_status') ? 'btn-outline-secondary' : 'btn-primary' }}" href="{{ route('courier.invoices.index') }}">All <span class="badge bg-light text-dark ms-1">{{ $counts['all'] }}</span></a>
    <a class="btn {{ request('payment_status') === 'paid' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('courier.invoices.index', ['payment_status' => 'paid']) }}">Paid <span class="badge bg-light text-dark ms-1">{{ $counts['paid'] }}</span></a>
    <a class="btn {{ request('payment_status') === 'unpaid' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('courier.invoices.index', ['payment_status' => 'unpaid']) }}">Unpaid <span class="badge bg-light text-dark ms-1">{{ $counts['unpaid'] }}</span></a>
</div>
<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th style="width:44px;"><input class="form-check-input" type="checkbox" data-select-all-invoices></th><th>Invoice</th><th>Customer</th><th>Mailbox</th><th>Status</th><th>Due</th><th>Total</th><th></th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td><input class="form-check-input" type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" form="sendSelectedInvoices"></td>
                    <td>
                        <strong>{{ $invoice->invoice_number }}</strong>
                        <br><small class="text-muted">{{ ucfirst($invoice->invoice_status) }}</small>
                        @if($invoice->creator)
                            <div class="text-muted small">Created by {{ optional($invoice->creator)->first_name ?: optional($invoice->creator)->full_name }}</div>
                        @endif
                        @if($invoice->updater && $invoice->updater->id !== $invoice->added_by)
                            <div class="text-muted small">Modified by {{ optional($invoice->updater)->first_name ?: optional($invoice->updater)->full_name }}</div>
                        @endif
                        @if($invoice->issuer)
                            <div class="text-muted small">Issued by {{ optional($invoice->issuer)->first_name ?: optional($invoice->issuer)->full_name }}</div>
                        @endif
                        @if($invoice->canceller)
                            <div class="text-muted small">Closed by {{ optional($invoice->canceller)->first_name ?: optional($invoice->canceller)->full_name }}</div>
                        @endif
                    </td>
                    <td>{{ optional(optional($invoice->customer)->user)->full_name ?? 'Unassigned' }}</td>
                    <td>{{ $invoice->mailbox }}</td>
                    <td>
                        <span class="badge {{ $invoice->isLocked() ? 'bg-secondary' : ($invoice->payment_status === 'paid' ? 'bg-success' : 'bg-warning text-dark') }}">{{ $invoice->isLocked() ? 'Voided / Archived' : ucfirst($invoice->payment_status) }}</span>
                    </td>
                    <td>{{ optional($invoice->due_date)->format('M d, Y') ?? '-' }}</td>
                    <td>JMD {{ number_format($invoice->total_jmd, 2) }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                            @if(! $invoice->isLocked() && in_array($invoice->payment_status, ['unpaid', 'partial']))
                                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#payInvoice{{ $invoice->id }}"><i class="fas fa-cash-register me-2"></i>Pay / Close</button></li>
                            @endif
                            <li><a href="{{ route('courier.invoices.show', $invoice) }}" class="dropdown-item"><i class="fas fa-up-right-from-square me-2"></i>Open</a></li>
                            @if(! $invoice->isLocked())
                                <li><a href="{{ route('courier.invoices.edit', $invoice) }}" class="dropdown-item"><i class="fas fa-pen me-2"></i>Edit</a></li>
                            @endif
                            <li><a href="{{ route('courier.invoices.download', $invoice) }}" class="dropdown-item"><i class="fas fa-download me-2"></i>Download</a></li>
                            @if(! $invoice->isLocked())
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('courier.invoices.void', $invoice) }}" data-confirm="Void this invoice?">
                                        @csrf
                                        <button class="dropdown-item text-danger"><i class="fas fa-ban me-2"></i>Void</button>
                                    </form>
                                </li>
                            @endif
                            </ul>
                        </div>
                        @if(! $invoice->isLocked() && in_array($invoice->payment_status, ['unpaid', 'partial']))
                            @include('courier.invoices._pay_modal', ['invoice' => $invoice, 'paymentMethods' => $paymentMethods])
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $invoices->links('pagination::bootstrap-5') }}</div>
</div>

<div class="modal fade" id="bulkInvoicePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ route('courier.invoices.bulk-generate') }}" class="modal-content">
            @csrf
            <input type="hidden" name="selected_only" value="1">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Invoice Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    <div class="col-md-8"><label class="form-label">Notes</label><input name="notes" class="form-control" placeholder="Optional note for generated invoices"></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th style="width:44px;"><input class="form-check-input" type="checkbox" data-bulk-select-all checked></th><th>Customer</th><th>Mailbox</th><th>Packages</th><th class="text-end">Estimated Package Total</th></tr></thead>
                        <tbody>
                            @forelse(($bulkPreviewGroups ?? collect()) as $group)
                                @php($customer = $group['customer'])
                                <tr>
                                    <td><input class="form-check-input" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" checked data-bulk-customer></td>
                                    <td>{{ optional($customer->user)->full_name ?: 'Customer' }}</td>
                                    <td>{{ $customer->mailbox }}</td>
                                    <td>{{ number_format($group['package_count']) }}</td>
                                    <td class="text-end">JMD {{ number_format($group['estimated_total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No uninvoiced manifested customer packages are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-primary" name="send_email" value="0"><i class="fas fa-file-circle-plus me-1"></i>Create Only</button>
                <button class="btn btn-success" name="send_email" value="1"><i class="fas fa-paper-plane me-1"></i>Create & Send</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('[data-select-all-invoices]')?.addEventListener('change', function () {
        document.querySelectorAll('input[name="invoice_ids[]"][form="sendSelectedInvoices"]').forEach(input => input.checked = this.checked);
    });
    document.querySelector('[data-bulk-select-all]')?.addEventListener('change', function () {
        document.querySelectorAll('[data-bulk-customer]').forEach(input => input.checked = this.checked);
    });
    document.querySelectorAll('[data-handoff-toggle]').forEach(function (toggle) {
        const modal = toggle.closest('.modal');
        const panel = modal ? modal.querySelector('[data-handoff-panel]') : null;
        const sync = function () {
            if (panel) panel.style.display = toggle.checked ? '' : 'none';
        };
        toggle.addEventListener('change', sync);
        sync();
    });
});
</script>
@endsection




