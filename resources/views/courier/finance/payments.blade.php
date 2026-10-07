@extends('layouts.app')

@section('title', 'Payments')

@section('content')
@include('courier.finance._toolbar', ['title' => 'Payments', 'subtitle' => 'Customer payment records and receipt references.', 'section' => 'payments'])
<form method="GET" class="panel p-3 mb-3"><div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Payment or reference"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['pending','approved','rejected','refunded'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Method</label><select name="payment_method_id" class="form-select"><option value="">All</option>@foreach($methods as $method)<option value="{{ $method->id }}" @selected(request('payment_method_id')==$method->id)>{{ $method->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
    <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
</div></form>
<div class="panel"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Payment</th><th>Invoice</th><th>Customer</th><th>Method</th><th>Status</th><th>Approved By</th><th>Paid</th><th>Points</th><th>Receipt</th><th class="text-end">Amount</th><th></th></tr></thead>
    <tbody>@forelse($payments as $payment)<tr>
        <td><strong>{{ $payment->payment_number }}</strong><br><small class="text-muted">{{ $payment->reference_number }}</small></td>
        <td>{{ optional($payment->invoice)->invoice_number ?: '-' }}</td>
        <td>{{ optional(optional($payment->customer)->user)->full_name ?? '-' }}</td>
        <td>{{ optional($payment->method)->name ?: '-' }}</td>
        <td><span class="badge badge-soft">{{ ucfirst($payment->status) }}</span></td>
        <td>
            @if($payment->approver)
                {{ $payment->approver->full_name ?: trim($payment->approver->first_name.' '.$payment->approver->last_name) }}
                <br><small class="text-muted">{{ optional($payment->approved_at)->format('M d, Y') }}</small>
            @else
                <span class="text-muted">-</span>
            @endif
        </td>
        <td>{{ optional($payment->paid_at)->format('M d, Y') ?? '-' }}</td>
        <td>
            @if(($payment->points_redeemed ?? 0) > 0)
                {{ number_format($payment->points_redeemed, 2) }}
                <br><small class="text-muted">JMD {{ number_format($payment->points_value_jmd, 2) }}</small>
            @else
                <span class="text-muted">-</span>
            @endif
        </td>
        <td>
            @if($payment->receipt_path)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-receipt-preview="{{ route('courier.finance.payments.receipt', $payment) }}"><i class="fas fa-eye me-1"></i>Preview</button>
            @else
                <form method="POST" action="{{ route('courier.finance.payments.receipt.upload', $payment) }}" enctype="multipart/form-data" class="d-flex gap-1">
                    @csrf
                    <input type="file" name="receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-upload"></i></button>
                </form>
            @endif
        </td>
        <td class="text-end">JMD {{ number_format($payment->amount, 2) }}</td>
        <td class="text-end">
            @if($payment->status !== 'approved')
                <form method="POST" action="{{ route('courier.finance.payments.approve', $payment) }}" enctype="multipart/form-data" class="d-grid gap-1">
                    @csrf
                    @if(! $payment->receipt_path)
                        <input type="file" name="receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    @endif
                    <button class="btn btn-sm btn-success">Approve</button>
                </form>
            @else
                <span class="text-muted">-</span>
            @endif
        </td>
    </tr>@empty<tr><td colspan="11" class="text-center text-muted py-4">No payments found.</td></tr>@endforelse</tbody>
</table></div><div class="p-3">{{ $payments->links('pagination::bootstrap-5') }}</div></div>
@include('courier.finance._receipt-preview-modal')
@endsection
