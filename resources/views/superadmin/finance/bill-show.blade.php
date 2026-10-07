@extends('layouts.app')

@section('title', $bill->bill_number)
@section('page_title', 'Review Bill')
@section('breadcrumb', $bill->bill_number)

@section('content')
@if($bill->isLocked())
    <div class="alert alert-secondary d-flex align-items-start gap-2">
        <i class="fas fa-lock mt-1"></i>
        <div>
            <strong>Voided and archived.</strong>
            This bill is permanently locked and cannot be reopened, modified, sent, or reviewed for payment.
            @if($bill->void_reason)<div class="mt-1">Reason: {{ $bill->void_reason }}</div>@endif
        </div>
    </div>
@endif

<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $bill->bill_number }}</h1>
        <p class="text-muted mb-0">{{ optional($bill->tenant)->company_name }} / {{ $bill->title }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(! $bill->isLocked())
            <form method="POST" action="{{ route('superadmin.finance.bills.send', $bill) }}">
                @csrf
                <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Bill</button>
            </form>
            <form method="POST" action="{{ route('superadmin.finance.bills.void', $bill) }}" data-confirm="Void this bill? It will be permanently locked and archived.">
                @csrf
                <button class="btn btn-outline-danger"><i class="fas fa-ban me-1"></i>Void Bill</button>
            </form>
        @endif
        <a href="{{ route('superadmin.finance.bills.download', $bill) }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Download Bill</a>
        <a href="{{ route('superadmin.finance.bills') }}" class="btn btn-outline-secondary">Back to Bills</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Total</div><div class="h4 mb-0">JMD {{ number_format($bill->total_jmd, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Confirmed Paid</div><div class="h4 mb-0 text-success">JMD {{ number_format($bill->amount_paid_jmd, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Outstanding</div><div class="h4 mb-0 text-danger">JMD {{ number_format($bill->balance_jmd, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Status</div><div class="h5 mb-0">{{ ucfirst(str_replace('_', ' ', $bill->status)) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-3"><div class="text-muted small">Sent</div><div class="h6 mb-0">{{ optional($bill->sent_at)->format('M d, Y h:i A') ?: 'Not sent' }}</div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel p-4">
            <h2 class="h5 mb-3">Bill Details</h2>
            @if($bill->isLocked())
                <div class="d-grid gap-2">
                    <div><span class="text-muted">Title:</span> <strong>{{ $bill->title }}</strong></div>
                    <div><span class="text-muted">Category:</span> <strong>{{ $bill->category }}</strong></div>
                    <div><span class="text-muted">Source:</span> <strong>{{ ucfirst(str_replace('_', ' ', $bill->source_type)) }}</strong></div>
                    <div><span class="text-muted">Issue Date:</span> <strong>{{ optional($bill->issue_date)->format('M d, Y') ?: '-' }}</strong></div>
                    <div><span class="text-muted">Due Date:</span> <strong>{{ optional($bill->due_date)->format('M d, Y') ?: '-' }}</strong></div>
                    @if($bill->public_description)<div class="mt-2"><strong>Description:</strong> <span class="text-muted">{{ $bill->public_description }}</span></div>@endif
                    @if($bill->notes)<div><strong>Notes:</strong> <span class="text-muted">{{ $bill->notes }}</span></div>@endif
                </div>
            @else
                <form method="POST" action="{{ route('superadmin.finance.bills.update', $bill) }}" class="row g-3">
                    @csrf @method('PUT')
                    <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $bill->title) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Category</label><input name="category" class="form-control" value="{{ old('category', $bill->category) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Source</label><select name="source_type" class="form-select">@foreach(['system_service','package_usage','subscription','other'] as $source)<option value="{{ $source }}" @selected(old('source_type', $bill->source_type) === $source)>{{ ucfirst(str_replace('_', ' ', $source)) }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', optional($bill->issue_date)->format('Y-m-d')) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($bill->due_date)->format('Y-m-d')) }}"></div>
                    <div class="col-md-6"><label class="form-label">Subtotal</label><input type="number" step="0.01" min="0" name="subtotal_jmd" class="form-control" value="{{ old('subtotal_jmd', $bill->subtotal_jmd) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Tax</label><input type="number" step="0.01" min="0" name="tax_jmd" class="form-control" value="{{ old('tax_jmd', $bill->tax_jmd) }}"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control">{{ old('description', $bill->description) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control">{{ old('notes', $bill->notes) }}</textarea></div>
                    <div class="col-12"><button class="btn btn-primary">Update Bill</button></div>
                </form>
            @endif
        </div>
    </div>
    <div class="col-lg-7">
        <div class="panel table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Payment</th><th>Method</th><th>Proof</th><th>Status</th><th class="text-end">Amount</th><th>Review</th></tr></thead>
                <tbody>
                    @forelse($bill->payments as $payment)
                        <tr>
                            <td>{{ optional($payment->submitted_at)->format('M d, Y h:i A') ?: optional($payment->created_at)->format('M d, Y') }}<br><span class="text-muted small">{{ $payment->reference_number ?: 'No reference' }}</span></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                            <td>@if($payment->receipt_path)<a href="{{ route('superadmin.finance.bill-payments.receipt', $payment) }}" class="btn btn-sm btn-outline-secondary">Download</a>@else - @endif</td>
                            <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span></td>
                            <td class="text-end">JMD {{ number_format($payment->amount_jmd, 2) }}</td>
                            <td style="min-width:240px;">
                                @if($bill->isLocked())
                                    <span class="text-muted small">Locked</span>
                                @else
                                    <form method="POST" action="{{ route('superadmin.finance.bill-payments.review', $payment) }}" class="d-grid gap-2">
                                        @csrf
                                        <select name="status" class="form-select form-select-sm">
                                            @foreach(['under_review','approved','partial','rejected'] as $status)
                                                <option value="{{ $status }}" @selected($payment->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.01" min="0" name="approved_amount_jmd" class="form-control form-control-sm" value="{{ $payment->amount_jmd }}" placeholder="Approved amount">
                                        <textarea name="admin_notes" class="form-control form-control-sm" rows="2" placeholder="Notes / outstanding balance request">{{ $payment->admin_notes }}</textarea>
                                        <button class="btn btn-sm btn-primary">Save Review</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No payment receipts submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
